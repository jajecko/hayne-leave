<?php
/**
 * HAYNE bulk administration endpoint for annual vacation profiles.
 *
 * This controller deliberately reuses Hayne_leave_policy_model::saveProfile()
 * so FIFO, rollover and managed entitlement semantics stay in one place.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Haynebulklimits extends CI_Controller
{
    private const MAX_EMPLOYEES_PER_REQUEST = 500;

    public function __construct()
    {
        parent::__construct();
        setUserContext($this);
        $this->assertAccess();
        $this->load->model('hayne_leave_policy_model');
        $this->load->model('hayne_caregiver_leave_model');
        $this->load->model('hayne_force_majeure_model');
        $this->load->model('hayne_childcare_model');
        $this->load->model('hayne_occasion_leave_model');
        $this->load->model('hayne_holiday_compensation_model');
    }

    public function save(): void
    {
        $employeeIds = $this->normalizeEmployeeIds($this->input->post('employee_ids', TRUE));
        $vacationTypeId = filter_var($this->input->post('vacation_type_id', TRUE), FILTER_VALIDATE_INT);
        $annualDays = filter_var($this->input->post('annual_days', TRUE), FILTER_VALIDATE_INT);
        $autoRenew = $this->input->post('auto_renew', TRUE) === '1';
        $overwriteExisting = $this->input->post('overwrite_existing', TRUE) === '1';
        $year = $this->validatedReturnYear($this->input->post('year', TRUE));

        if (empty($employeeIds)) {
            $this->flashAndReturn('Zaznacz co najmniej jednego pracownika.', $year);
            return;
        }
        if (count($employeeIds) > self::MAX_EMPLOYEES_PER_REQUEST) {
            $this->flashAndReturn('Jednorazowo można zaktualizować maksymalnie 500 pracowników.', $year);
            return;
        }
        if (
            $vacationTypeId === FALSE || $vacationTypeId <= 0 ||
            $annualDays === FALSE || $annualDays < 0 || $annualDays > 366
        ) {
            $this->flashAndReturn('Nieprawidłowe ustawienia limitu grupowego.', $year);
            return;
        }

        $this->load->model('users_model');
        $this->load->model('types_model');
        if (empty($this->types_model->getTypes((int) $vacationTypeId))) {
            $this->flashAndReturn('Nie znaleziono wybranego rodzaju urlopu.', $year);
            return;
        }

        $collision = $this->activeStatutoryPolicyForType((int) $vacationTypeId);
        if ($collision !== NULL) {
            $this->flashAndReturn(
                'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '. Wybierz inny typ urlopu wypoczynkowego.',
                $year
            );
            return;
        }

        $activeEmployees = [];
        foreach ($this->users_model->getUsers() as $employee) {
            if ((int) $employee['active'] === 1) {
                $activeEmployees[(int) $employee['id']] = $employee;
            }
        }

        $saved = 0;
        $skippedExisting = 0;
        $skippedInactive = 0;
        $skippedTypeMismatch = 0;
        $failed = 0;

        foreach ($employeeIds as $employeeId) {
            if (!isset($activeEmployees[$employeeId])) {
                $skippedInactive++;
                continue;
            }

            $existing = $this->hayne_leave_policy_model->getProfile($employeeId);
            if (!empty($existing)) {
                if (!$overwriteExisting) {
                    $skippedExisting++;
                    continue;
                }

                // Changing the leave type in bulk is intentionally forbidden.
                // A profile can already own managed pools and deserves explicit,
                // single-employee review instead of a mass mutation.
                if ((int) $existing['vacation_type_id'] !== (int) $vacationTypeId) {
                    $skippedTypeMismatch++;
                    continue;
                }
            }

            try {
                $this->hayne_leave_policy_model->saveProfile(
                    $employeeId,
                    (int) $vacationTypeId,
                    (int) $annualDays,
                    $autoRenew
                );
                $saved++;
            } catch (Throwable $exception) {
                $failed++;
                log_message(
                    'error',
                    'HAYNE bulk leave profile save failed for employee #' . $employeeId . ': ' . $exception->getMessage()
                );
            }
        }

        $parts = ['Zapisano limit dla ' . $saved . ' pracowników.'];
        if ($skippedExisting > 0) {
            $parts[] = 'Pominięto ' . $skippedExisting . ' istniejących konfiguracji.';
        }
        if ($skippedTypeMismatch > 0) {
            $parts[] = $skippedTypeMismatch . ' osób ma inny typ urlopu i wymaga edycji pojedynczej.';
        }
        if ($skippedInactive > 0) {
            $parts[] = 'Pominięto ' . $skippedInactive . ' nieaktywnych lub nieistniejących pracowników.';
        }
        if ($failed > 0) {
            $parts[] = 'Nie udało się zapisać ' . $failed . ' pozycji; szczegóły zapisano w logu.';
        }

        $this->flashAndReturn(implode(' ', $parts), $year);
    }

    /** @return int[] */
    private function normalizeEmployeeIds($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $value) {
            $id = filter_var($value, FILTER_VALIDATE_INT);
            if ($id !== FALSE && $id > 0) {
                $result[(int) $id] = (int) $id;
            }
        }
        return array_values($result);
    }

    private function validatedReturnYear($raw): int
    {
        $currentYear = (int) date('Y');
        $year = filter_var($raw, FILTER_VALIDATE_INT);
        if ($year === FALSE || $year < ($currentYear - 5) || $year > ($currentYear + 1)) {
            return $currentYear;
        }
        return (int) $year;
    }

    private function flashAndReturn(string $message, int $year): void
    {
        $this->session->set_flashdata('msg', $message);
        redirect('haynelimits?year=' . $year . '#hayneAnnualLimits');
    }

    private function activeStatutoryPolicyForType(int $leaveTypeId): ?string
    {
        $policies = [
            ['policy' => $this->hayne_caregiver_leave_model->getPolicy(), 'label' => 'urlop opiekuńczy'],
            ['policy' => $this->hayne_force_majeure_model->getPolicy(), 'label' => 'siła wyższa'],
            ['policy' => $this->hayne_childcare_model->getPolicy(), 'label' => 'opieka nad dzieckiem do 14 lat'],
            ['policy' => $this->hayne_occasion_leave_model->getPolicy(), 'label' => 'urlop okolicznościowy'],
            ['policy' => $this->hayne_holiday_compensation_model->getPolicy(), 'label' => 'dzień wolny za święto'],
        ];

        foreach ($policies as $item) {
            $policy = $item['policy'];
            if (
                !empty($policy) &&
                (int) $policy['enabled'] === 1 &&
                (int) $policy['leave_type_id'] === $leaveTypeId
            ) {
                return (string) $item['label'];
            }
        }
        return NULL;
    }

    private function assertAccess(): void
    {
        if (!$this->is_hr && !$this->is_admin) {
            show_error('Forbidden', 403);
        }
    }
}
