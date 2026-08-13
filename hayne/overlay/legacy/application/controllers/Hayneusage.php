<?php
/**
 * HR surface for reconciling leave already used on paper before HAYNE Leave
 * became the operational request system.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayneusage extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        setUserContext($this);
        $this->assertAccess();
        $this->load->helper('form');
        $this->load->model('users_model');
        $this->load->model('hayne_usage_correction_model');
        $this->load->model('hayne_leave_policy_model');
        $this->load->model('hayne_on_demand_model');
        $this->load->model('hayne_caregiver_leave_model');
        $this->load->model('hayne_force_majeure_model');
        $this->load->model('hayne_childcare_model');
        $this->load->model('hayne_occasion_leave_model');
        $this->load->model('hayne_holiday_compensation_model');
    }

    public function edit(int $employeeId): void
    {
        $employee = $this->employee($employeeId);
        $year = $this->selectedYear($this->input->get('year', TRUE));

        $data = getUserContext($this);
        $data['title'] = 'Korekta wykorzystania';
        $data['help'] = '';
        $data['employee'] = $employee;
        $data['year'] = $year;
        $data['current_year'] = (int) date('Y');
        $data['editable'] = $year === (int) date('Y');
        $data['corrections'] = $this->hayne_usage_correction_model->getSimpleMap($employeeId, $year);

        $profile = $this->hayne_leave_policy_model->getProfile($employeeId);
        if (!empty($profile)) {
            $this->hayne_leave_policy_model->ensureYear($employeeId, $year);
        }
        $data['vacation_profile'] = $profile;
        $data['vacation_summary'] = empty($profile)
            ? NULL
            : $this->hayne_leave_policy_model->getYearSummary($employeeId, $year);
        $data['on_demand_summary'] = empty($profile)
            ? NULL
            : $this->hayne_on_demand_model->getSummary($employeeId, $year);

        $data['caregiver_summary'] = $this->hayne_caregiver_leave_model->getSummary($employeeId, $year);
        $data['force_majeure_summary'] = $this->hayne_force_majeure_model->getSummary($employeeId, $year);
        $data['childcare_summary'] = $this->hayne_childcare_model->getSummary($employeeId, $year);

        $occasionPolicy = $this->hayne_occasion_leave_model->getPolicy();
        $data['occasion_policy'] = $occasionPolicy;
        $data['occasion_definitions'] = $this->hayne_occasion_leave_model->getEventDefinitions();
        $data['occasion_corrections'] = $this->decorateOccasionCorrections(
            $this->hayne_usage_correction_model->getOccasionCorrections($employeeId, $year),
            $data['occasion_definitions']
        );

        $holidayPolicy = $this->hayne_holiday_compensation_model->getPolicy();
        $data['holiday_policy'] = $holidayPolicy;
        $data['holiday_grants'] = $this->holidayGrantsForEmployee($employeeId, $year);
        $data['holiday_manual_usage'] = $this->hayne_usage_correction_model->getHolidayGrantUsage($employeeId, $year);
        $data['correction_history'] = $this->hayne_usage_correction_model->getHistory($employeeId, $year, 20);
        $data['flash_partial_view'] = $this->load->view('templates/flash', $data, TRUE);

        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('hayneusage/edit', $data);
        $this->load->view('templates/footer');
    }

    public function save(int $employeeId): void
    {
        $employee = $this->employee($employeeId);
        $year = $this->selectedYear($this->input->post('year', TRUE));
        $currentYear = (int) date('Y');
        if ($year !== $currentYear) {
            $this->flashAndReturn($employeeId, $year, 'Korekty wykorzystania można zapisywać tylko dla bieżącego roku.');
            return;
        }

        $actorId = (int) $this->session->userdata('id');
        $current = $this->hayne_usage_correction_model->getSimpleMap($employeeId, $year);

        try {
            $vacationUsed = $this->wholeDays('vacation_used', 366);
            $onDemandUsed = $this->wholeDays('on_demand_used', 4);
            $caregiverUsed = $this->wholeDays('caregiver_used', 5);
            $forceUsed = $this->wholeDays('force_majeure_used', 2);
            $childcareUsed = $this->wholeDays('childcare_used', 2);

            if ($onDemandUsed > $vacationUsed) {
                throw new InvalidArgumentException('Urlop na żądanie musi być częścią wykorzystanego urlopu wypoczynkowego.');
            }

            $this->db->trans_begin();

            $profile = $this->hayne_leave_policy_model->getProfile($employeeId);
            if (empty($profile)) {
                if ($vacationUsed > 0 || $onDemandUsed > 0) {
                    throw new InvalidArgumentException('Najpierw przydziel pracownikowi roczną pulę urlopu wypoczynkowego.');
                }
            } else {
                $this->hayne_leave_policy_model->ensureYear($employeeId, $year);
                $vacationSummary = $this->hayne_leave_policy_model->getYearSummary($employeeId, $year);
                $currentVacationManual = (int) $current['vacation_regular'] + (int) $current['on_demand'];
                $actualVacation = max(0.0, (float) $vacationSummary['used'] - $currentVacationManual);
                if (($actualVacation + $vacationUsed) > (float) $vacationSummary['granted']) {
                    throw new InvalidArgumentException(
                        'Korekta urlopu wypoczynkowego przekracza dostępną pulę. W HAYNE jest już rozliczone ' .
                        $this->formatDays($actualVacation) . ' dni.'
                    );
                }

                $onDemandSummary = $this->hayne_on_demand_model->getSummary($employeeId, $year);
                $actualOnDemand = max(0.0, (float) $onDemandSummary['used'] - (int) $current['on_demand']);
                if (($actualOnDemand + $onDemandUsed) > 4) {
                    throw new InvalidArgumentException(
                        'Korekta urlopu na żądanie przekracza limit 4 dni. W HAYNE jest już zarezerwowane lub wykorzystane ' .
                        $this->formatDays($actualOnDemand) . ' dni.'
                    );
                }

                $this->hayne_usage_correction_model->setVacation(
                    $employeeId,
                    $year,
                    $vacationUsed,
                    $onDemandUsed,
                    (int) $profile['vacation_type_id'],
                    $actorId
                );
            }

            $this->saveAnnualStatutory(
                $employeeId,
                $year,
                'caregiver',
                $caregiverUsed,
                (int) $current['caregiver'],
                $this->hayne_caregiver_leave_model->getSummary($employeeId, $year),
                $this->hayne_caregiver_leave_model->getPolicy(),
                5,
                $actorId
            );
            $this->saveAnnualStatutory(
                $employeeId,
                $year,
                'force_majeure',
                $forceUsed,
                (int) $current['force_majeure'],
                $this->hayne_force_majeure_model->getSummary($employeeId, $year),
                $this->hayne_force_majeure_model->getPolicy(),
                2,
                $actorId
            );

            $childcareSummary = $this->hayne_childcare_model->getSummary($employeeId, $year);
            $childcarePolicy = $this->hayne_childcare_model->getPolicy();
            if ($childcareSummary === NULL) {
                if ($childcareUsed > 0) {
                    throw new InvalidArgumentException('Pracownik nie ma przyznanej puli opieki nad dzieckiem na ten rok.');
                }
                if ((int) $current['childcare'] > 0 && !empty($childcarePolicy)) {
                    $this->hayne_usage_correction_model->setChildcare(
                        $employeeId,
                        $year,
                        0,
                        (int) $childcarePolicy['leave_type_id'],
                        $actorId
                    );
                }
            } else {
                $actualChildcare = max(0.0, (float) $childcareSummary['used'] - (int) $current['childcare']);
                if (($actualChildcare + $childcareUsed) > (float) $childcareSummary['limit']) {
                    throw new InvalidArgumentException('Korekta opieki nad dzieckiem przekracza przyznany limit.');
                }
                $this->hayne_usage_correction_model->setChildcare(
                    $employeeId,
                    $year,
                    $childcareUsed,
                    (int) $childcareSummary['leave_type_id'],
                    $actorId
                );
            }

            $this->saveOccasionCorrection($employeeId, $year, $actorId);
            $this->saveHolidayCorrections($employeeId, $year, $actorId);

            if ($this->db->trans_status() === FALSE) {
                throw new RuntimeException('Nie udało się zapisać korekt wykorzystania.');
            }
            $this->db->trans_commit();
            $this->flashAndReturn($employeeId, $year, 'Korekta wykorzystania została zapisana. Salda zostały przeliczone.');
        } catch (Throwable $exception) {
            if ($this->db->trans_status() !== FALSE) {
                $this->db->trans_rollback();
            } else {
                $this->db->trans_rollback();
            }
            log_message('error', 'HAYNE usage correction save failed: ' . $exception->getMessage());
            $this->flashAndReturn($employeeId, $year, 'Nie udało się zapisać korekty: ' . $exception->getMessage());
        }
    }

    private function saveAnnualStatutory(
        int $employeeId,
        int $year,
        string $code,
        int $newManual,
        int $currentManual,
        ?array $summary,
        ?array $policy,
        int $hardLimit,
        int $actorId
    ): void {
        if ($summary === NULL || empty($policy) || (int) $policy['enabled'] !== 1) {
            if ($newManual > 0) {
                throw new InvalidArgumentException('Wybrana pula ustawowa nie jest skonfigurowana.');
            }
            if ($currentManual > 0 && !empty($policy)) {
                if ($code === 'caregiver') {
                    $this->hayne_usage_correction_model->setCaregiver($employeeId, $year, 0, (int) $policy['leave_type_id'], $actorId);
                } else {
                    $this->hayne_usage_correction_model->setForceMajeure($employeeId, $year, 0, (int) $policy['leave_type_id'], $actorId);
                }
            }
            return;
        }

        $actual = max(0.0, (float) $summary['used'] - $currentManual);
        $limit = isset($summary['limit']) ? (float) $summary['limit'] : (float) $hardLimit;
        if (($actual + $newManual) > $limit) {
            throw new InvalidArgumentException(
                'Korekta przekracza limit ' . $this->formatDays($limit) . ' dni; w HAYNE jest już wykorzystane lub zarezerwowane ' .
                $this->formatDays($actual) . ' dni.'
            );
        }
        if ($code === 'caregiver') {
            $this->hayne_usage_correction_model->setCaregiver(
                $employeeId,
                $year,
                $newManual,
                (int) $summary['leave_type_id'],
                $actorId
            );
        } else {
            $this->hayne_usage_correction_model->setForceMajeure(
                $employeeId,
                $year,
                $newManual,
                (int) $summary['leave_type_id'],
                $actorId
            );
        }
    }

    private function saveOccasionCorrection(int $employeeId, int $year, int $actorId): void
    {
        $remove = $this->input->post('remove_occasion', TRUE);
        if (is_array($remove)) {
            foreach ($remove as $referenceKey) {
                $referenceKey = trim((string) $referenceKey);
                if ($referenceKey !== '') {
                    $this->hayne_usage_correction_model->removeOccasion($employeeId, $year, $referenceKey, $actorId);
                }
            }
        }

        $eventCode = trim((string) $this->input->post('occasion_event_code', TRUE));
        $eventDate = trim((string) $this->input->post('occasion_event_date', TRUE));
        $rawDays = $this->input->post('occasion_days', TRUE);
        if ($eventCode === '' && $eventDate === '' && ($rawDays === NULL || $rawDays === '')) {
            return;
        }

        $definitions = $this->hayne_occasion_leave_model->getEventDefinitions();
        if (!isset($definitions[$eventCode])) {
            throw new InvalidArgumentException('Wybierz prawidłowe zdarzenie urlopu okolicznościowego.');
        }
        $days = filter_var($rawDays, FILTER_VALIDATE_INT);
        if ($days === FALSE || $days <= 0 || $days > (int) $definitions[$eventCode]['days']) {
            throw new InvalidArgumentException('Nieprawidłowa liczba wykorzystanych dni urlopu okolicznościowego.');
        }
        $policy = $this->hayne_occasion_leave_model->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Urlop okolicznościowy nie jest skonfigurowany.');
        }
        $referenceKey = $eventCode . '|' . $eventDate;
        $currentCorrection = $this->hayne_usage_correction_model->getCorrection($employeeId, $year, 'occasion', $referenceKey);
        $excludeLeaveId = empty($currentCorrection) ? NULL : (int) $currentCorrection['leave_id'];

        $this->hayne_occasion_leave_model->assertRequestAllowed(
            $employeeId,
            (int) $policy['leave_type_id'],
            $eventDate,
            $eventDate,
            (float) $days,
            LMS_ACCEPTED,
            ['event_code' => $eventCode, 'event_date' => $eventDate],
            $excludeLeaveId
        );
        $this->hayne_usage_correction_model->setOccasion(
            $employeeId,
            $year,
            $eventCode,
            $eventDate,
            (int) $days,
            (int) $definitions[$eventCode]['days'],
            (int) $policy['leave_type_id'],
            $actorId
        );
    }

    private function saveHolidayCorrections(int $employeeId, int $year, int $actorId): void
    {
        $policy = $this->hayne_holiday_compensation_model->getPolicy();
        $selected = $this->input->post('holiday_grants', TRUE);
        $selectedIds = [];
        if (is_array($selected)) {
            foreach ($selected as $value) {
                $id = filter_var($value, FILTER_VALIDATE_INT);
                if ($id !== FALSE && $id > 0) {
                    $selectedIds[(int) $id] = TRUE;
                }
            }
        }

        $manualMap = $this->hayne_usage_correction_model->getHolidayGrantUsage($employeeId, $year);
        $grants = $this->holidayGrantsForEmployee($employeeId, $year);
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            if (!empty($selectedIds)) {
                throw new InvalidArgumentException('Dni wolne za święto nie są skonfigurowane.');
            }
            return;
        }

        foreach ($grants as $grant) {
            $grantId = (int) $grant['id'];
            $manual = isset($manualMap[$grantId]) && (int) $manualMap[$grantId] > 0;
            $realReserved = max(0, (int) $grant['reserved'] - ($manual ? 1 : 0));
            $wantManual = isset($selectedIds[$grantId]);
            if ($wantManual && $realReserved > 0) {
                throw new InvalidArgumentException('Nie można oznaczyć jako ręcznie wykorzystanego dnia za święto, który ma już wniosek w HAYNE.');
            }
            $this->hayne_usage_correction_model->setHolidayGrant(
                $employeeId,
                $year,
                $grantId,
                $wantManual,
                (int) $policy['leave_type_id'],
                $actorId
            );
        }
    }

    /** @return array<string, mixed> */
    private function employee(int $employeeId): array
    {
        if ($employeeId <= 0) {
            show_404();
        }
        $employee = $this->users_model->getUsers($employeeId);
        if (empty($employee) || (int) $employee['active'] !== 1) {
            show_404();
        }
        return $employee;
    }

    private function selectedYear($raw): int
    {
        $current = (int) date('Y');
        $year = filter_var($raw, FILTER_VALIDATE_INT);
        if ($year === FALSE || $year < ($current - 5) || $year > $current) {
            return $current;
        }
        return (int) $year;
    }

    private function wholeDays(string $field, int $max): int
    {
        $raw = $this->input->post($field, TRUE);
        if ($raw === NULL || $raw === '') {
            return 0;
        }
        $value = filter_var($raw, FILTER_VALIDATE_INT);
        if ($value === FALSE || $value < 0 || $value > $max) {
            throw new InvalidArgumentException('Pole „' . $field . '” musi zawierać pełną liczbę dni od 0 do ' . $max . '.');
        }
        return (int) $value;
    }

    /** @return array<int, array<string, mixed>> */
    private function holidayGrantsForEmployee(int $employeeId, int $year): array
    {
        $rows = $this->hayne_holiday_compensation_model->getGrants();
        return array_values(array_filter($rows, static function (array $row) use ($employeeId, $year): bool {
            return (int) $row['employee_id'] === $employeeId
                && (int) substr((string) $row['source_holiday_date'], 0, 4) === $year;
        }));
    }

    /** @return array<int, array<string, mixed>> */
    private function decorateOccasionCorrections(array $rows, array $definitions): array
    {
        foreach ($rows as &$row) {
            $parts = explode('|', (string) $row['reference_key'], 2);
            $row['event_code'] = $parts[0] ?? '';
            $row['event_date'] = $parts[1] ?? '';
            $definition = $definitions[$row['event_code']] ?? NULL;
            $row['event_label'] = $definition === NULL ? $row['event_code'] : $definition['label'];
        }
        unset($row);
        return $rows;
    }

    private function flashAndReturn(int $employeeId, int $year, string $message): void
    {
        $this->session->set_flashdata('msg', $message);
        redirect('hayneusage/edit/' . $employeeId . '?year=' . $year);
    }

    private function formatDays(float $days): string
    {
        if (floor($days) == $days) {
            return (string) (int) $days;
        }
        return rtrim(rtrim(number_format($days, 2, '.', ''), '0'), '.');
    }

    private function assertAccess(): void
    {
        if (!$this->is_hr && !$this->is_admin) {
            show_error('Forbidden', 403);
        }
    }
}
