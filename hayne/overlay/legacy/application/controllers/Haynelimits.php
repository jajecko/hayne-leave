<?php
/**
 * HAYNE administration surface for annual vacation and statutory leave settings.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Haynelimits extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        setUserContext($this);
        $this->assertAccess();
        $this->load->helper('form');
        $this->load->model('hayne_leave_policy_model');
        $this->load->model('hayne_caregiver_leave_model');
        $this->load->model('hayne_force_majeure_model');
        $this->load->model('hayne_childcare_model');
        $this->load->model('hayne_occasion_leave_model');
        $this->load->model('hayne_holiday_compensation_model');
        $this->load->model('hayne_credit_exemption_model');
    }

    public function index(): void
    {
        $currentYear = (int) date('Y');
        $requestedYear = $this->input->get('year', TRUE);
        $year = filter_var($requestedYear, FILTER_VALIDATE_INT);
        if ($year === FALSE || $year < ($currentYear - 5) || $year > ($currentYear + 1)) {
            $year = $currentYear;
        }

        // This is intentionally idempotent. Opening the administration page
        // doubles as a safe yearly synchronization for configured employees.
        $this->hayne_leave_policy_model->ensureYearForAll((int) $year);

        $data = getUserContext($this);
        $this->load->model('users_model');
        $this->load->model('types_model');
        $data['employees'] = $this->users_model->getUsers();
        $data['types'] = $this->types_model->getTypes();
        $data['profiles'] = $this->hayne_leave_policy_model->getProfilesWithSummary((int) $year);
        $data['selected_year'] = (int) $year;
        $data['current_year'] = $currentYear;
        $data['default_type'] = (int) $this->config->item('default_leave_type');
        $data['caregiver_policy'] = $this->hayne_caregiver_leave_model->getPolicy();
        $data['force_majeure_policy'] = $this->hayne_force_majeure_model->getPolicy();
        $data['childcare_policy'] = $this->hayne_childcare_model->getPolicy();
        $data['childcare_allocations'] = $this->hayne_childcare_model->getAllocationMap((int) $year);
        $data['occasion_policy'] = $this->hayne_occasion_leave_model->getPolicy();
        $data['official_summons_policy'] = $this->hayne_credit_exemption_model->getOfficialSummonsPolicy();

        foreach ($data['employees'] as $employee) {
            if ((int) $employee['active'] !== 1) {
                continue;
            }
            if (!empty($data['caregiver_policy']) && (int) $data['caregiver_policy']['enabled'] === 1) {
                $this->hayne_caregiver_leave_model->ensureYear((int) $employee['id'], (int) $year);
            }
            if (!empty($data['force_majeure_policy']) && (int) $data['force_majeure_policy']['enabled'] === 1) {
                $this->hayne_force_majeure_model->ensureYear((int) $employee['id'], (int) $year);
            }
        }
        if (!empty($data['childcare_policy']) && (int) $data['childcare_policy']['enabled'] === 1) {
            $this->hayne_childcare_model->ensureConfiguredYearForAll((int) $year);
        }

        $editEmployeeId = filter_var($this->input->get('edit', TRUE), FILTER_VALIDATE_INT);
        $data['edit_profile'] = $editEmployeeId === FALSE
            ? NULL
            : $this->hayne_leave_policy_model->getProfile((int) $editEmployeeId);

        $data['title'] = 'Limity urlopowe';
        $data['help'] = '';
        $data['flash_partial_view'] = $this->load->view('templates/flash', $data, TRUE);
        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('haynelimits/index', $data);
        $this->load->view('templates/footer');
    }

    public function save(): void
    {
        $employeeId = filter_var($this->input->post('employee_id', TRUE), FILTER_VALIDATE_INT);
        $vacationTypeId = filter_var($this->input->post('vacation_type_id', TRUE), FILTER_VALIDATE_INT);
        $annualDays = filter_var($this->input->post('annual_days', TRUE), FILTER_VALIDATE_INT);
        $autoRenew = $this->input->post('auto_renew', TRUE) === '1';

        if (
            $employeeId === FALSE || $employeeId <= 0 ||
            $vacationTypeId === FALSE || $vacationTypeId <= 0 ||
            $annualDays === FALSE || $annualDays < 0 || $annualDays > 366
        ) {
            $this->session->set_flashdata('msg', 'Nieprawidłowe ustawienia limitu urlopowego.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('users_model');
        $this->load->model('types_model');
        if (empty($this->users_model->getUsers((int) $employeeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono pracownika.');
            redirect('haynelimits');
            return;
        }
        if (empty($this->types_model->getTypes((int) $vacationTypeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono wybranego rodzaju urlopu.');
            redirect('haynelimits');
            return;
        }

        $collision = $this->activeStatutoryPolicyForType((int) $vacationTypeId);
        if ($collision !== NULL) {
            $this->session->set_flashdata(
                'msg',
                'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '. Wybierz inny typ urlopu wypoczynkowego.'
            );
            redirect('haynelimits');
            return;
        }

        try {
            $this->hayne_leave_policy_model->saveProfile(
                (int) $employeeId,
                (int) $vacationTypeId,
                (int) $annualDays,
                $autoRenew
            );
            $this->session->set_flashdata('msg', 'Limit urlopowy został zapisany.');
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE leave profile save failed: ' . $exception->getMessage());
            $this->session->set_flashdata('msg', 'Nie udało się zapisać limitu: ' . $exception->getMessage());
        }

        redirect('haynelimits?edit=' . (int) $employeeId);
    }

    public function saveCaregiverPolicy(): void
    {
        $leaveTypeId = filter_var($this->input->post('caregiver_type_id', TRUE), FILTER_VALIDATE_INT);
        $enabled = $this->input->post('caregiver_enabled', TRUE) === '1';

        if ($leaveTypeId === FALSE || $leaveTypeId <= 0) {
            $this->session->set_flashdata('msg', 'Wybierz prawidłowy rodzaj urlopu opiekuńczego.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('types_model');
        if (empty($this->types_model->getTypes((int) $leaveTypeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono wybranego rodzaju urlopu opiekuńczego.');
            redirect('haynelimits');
            return;
        }

        if ($enabled) {
            if ($this->isVacationTypeInUse((int) $leaveTypeId)) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany jako urlop wypoczynkowy.');
                redirect('haynelimits');
                return;
            }
            $collision = $this->activeStatutoryPolicyForType((int) $leaveTypeId, 'caregiver');
            if ($collision !== NULL) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '.');
                redirect('haynelimits');
                return;
            }
        }

        try {
            $this->hayne_caregiver_leave_model->savePolicy((int) $leaveTypeId, $enabled);

            if ($enabled) {
                $this->load->model('users_model');
                foreach ($this->users_model->getUsers() as $employee) {
                    if ((int) $employee['active'] === 1) {
                        $this->hayne_caregiver_leave_model->ensureCurrentYear((int) $employee['id']);
                    }
                }
            }

            $this->session->set_flashdata(
                'msg',
                $enabled
                    ? 'Urlop opiekuńczy: zapisano limit 5 dni rocznie.'
                    : 'Urlop opiekuńczy został wyłączony.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE caregiver policy save failed: ' . $exception->getMessage());
            $this->session->set_flashdata(
                'msg',
                'Nie udało się zapisać ustawień urlopu opiekuńczego: ' . $exception->getMessage()
            );
        }

        redirect('haynelimits');
    }

    public function saveForceMajeurePolicy(): void
    {
        $leaveTypeId = filter_var($this->input->post('force_majeure_type_id', TRUE), FILTER_VALIDATE_INT);
        $enabled = $this->input->post('force_majeure_enabled', TRUE) === '1';

        if ($leaveTypeId === FALSE || $leaveTypeId <= 0) {
            $this->session->set_flashdata('msg', 'Wybierz prawidłowy rodzaj zwolnienia z powodu siły wyższej.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('types_model');
        if (empty($this->types_model->getTypes((int) $leaveTypeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono wybranego rodzaju zwolnienia z powodu siły wyższej.');
            redirect('haynelimits');
            return;
        }

        if ($enabled) {
            if ($this->isVacationTypeInUse((int) $leaveTypeId)) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany jako urlop wypoczynkowy.');
                redirect('haynelimits');
                return;
            }
            $collision = $this->activeStatutoryPolicyForType((int) $leaveTypeId, 'force_majeure');
            if ($collision !== NULL) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '.');
                redirect('haynelimits');
                return;
            }
        }

        try {
            $this->hayne_force_majeure_model->savePolicy((int) $leaveTypeId, $enabled);

            if ($enabled) {
                $this->load->model('users_model');
                foreach ($this->users_model->getUsers() as $employee) {
                    if ((int) $employee['active'] === 1) {
                        $this->hayne_force_majeure_model->ensureCurrentYear((int) $employee['id']);
                    }
                }
            }

            $this->session->set_flashdata(
                'msg',
                $enabled
                    ? 'Siła wyższa: zapisano limit 2 dni rocznie.'
                    : 'Zwolnienie z powodu siły wyższej zostało wyłączone.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE force-majeure policy save failed: ' . $exception->getMessage());
            $this->session->set_flashdata(
                'msg',
                'Nie udało się zapisać ustawień siły wyższej: ' . $exception->getMessage()
            );
        }

        redirect('haynelimits');
    }

    public function saveChildcarePolicy(): void
    {
        $leaveTypeId = filter_var($this->input->post('childcare_type_id', TRUE), FILTER_VALIDATE_INT);
        $enabled = $this->input->post('childcare_enabled', TRUE) === '1';

        if ($leaveTypeId === FALSE || $leaveTypeId <= 0) {
            $this->session->set_flashdata('msg', 'Wybierz prawidłowy rodzaj opieki nad dzieckiem do 14 lat.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('types_model');
        if (empty($this->types_model->getTypes((int) $leaveTypeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono wybranego rodzaju opieki nad dzieckiem.');
            redirect('haynelimits');
            return;
        }

        if ($enabled) {
            if ($this->isVacationTypeInUse((int) $leaveTypeId)) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany jako urlop wypoczynkowy.');
                redirect('haynelimits');
                return;
            }
            $collision = $this->activeStatutoryPolicyForType((int) $leaveTypeId, 'childcare');
            if ($collision !== NULL) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '.');
                redirect('haynelimits');
                return;
            }
        }

        try {
            $this->hayne_childcare_model->savePolicy((int) $leaveTypeId, $enabled);
            if ($enabled) {
                $this->hayne_childcare_model->ensureConfiguredYearForAll((int) date('Y'));
            }
            $this->session->set_flashdata(
                'msg',
                $enabled
                    ? 'Opieka nad dzieckiem: polityka art. 188 została włączona.'
                    : 'Opieka nad dzieckiem została wyłączona.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE childcare policy save failed: ' . $exception->getMessage());
            $this->session->set_flashdata(
                'msg',
                'Nie udało się zapisać ustawień opieki nad dzieckiem: ' . $exception->getMessage()
            );
        }

        redirect('haynelimits');
    }

    public function saveOccasionPolicy(): void
    {
        $leaveTypeId = filter_var($this->input->post('occasion_type_id', TRUE), FILTER_VALIDATE_INT);
        $enabled = $this->input->post('occasion_enabled', TRUE) === '1';

        if ($leaveTypeId === FALSE || $leaveTypeId <= 0) {
            $this->session->set_flashdata('msg', 'Wybierz prawidłowy rodzaj urlopu okolicznościowego.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('types_model');
        if (empty($this->types_model->getTypes((int) $leaveTypeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono wybranego rodzaju urlopu okolicznościowego.');
            redirect('haynelimits');
            return;
        }

        if ($enabled) {
            if ($this->isVacationTypeInUse((int) $leaveTypeId)) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany jako urlop wypoczynkowy.');
                redirect('haynelimits');
                return;
            }
            $collision = $this->activeStatutoryPolicyForType((int) $leaveTypeId, 'occasion');
            if ($collision !== NULL) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '.');
                redirect('haynelimits');
                return;
            }
        }

        try {
            $this->hayne_occasion_leave_model->savePolicy((int) $leaveTypeId, $enabled);
            $this->session->set_flashdata(
                'msg',
                $enabled
                    ? 'Urlop okolicznościowy został włączony. Limit jest liczony osobno dla każdego zdarzenia.'
                    : 'Urlop okolicznościowy został wyłączony.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE occasion policy save failed: ' . $exception->getMessage());
            $this->session->set_flashdata(
                'msg',
                'Nie udało się zapisać ustawień urlopu okolicznościowego: ' . $exception->getMessage()
            );
        }

        redirect('haynelimits');
    }

    public function saveOfficialSummonsPolicy(): void
    {
        $leaveTypeId = filter_var($this->input->post('official_summons_type_id', TRUE), FILTER_VALIDATE_INT);
        $enabled = $this->input->post('official_summons_enabled', TRUE) === '1';

        if ($leaveTypeId === FALSE || $leaveTypeId <= 0) {
            $this->session->set_flashdata('msg', 'Wybierz prawidłowy rodzaj zwolnienia na wezwanie organu.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('types_model');
        if (empty($this->types_model->getTypes((int) $leaveTypeId))) {
            $this->session->set_flashdata('msg', 'Nie znaleziono wybranego rodzaju zwolnienia na wezwanie organu.');
            redirect('haynelimits');
            return;
        }

        if ($enabled) {
            if ($this->isVacationTypeInUse((int) $leaveTypeId)) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany jako urlop wypoczynkowy.');
                redirect('haynelimits');
                return;
            }
            $collision = $this->activeStatutoryPolicyForType((int) $leaveTypeId, 'official_summons');
            if ($collision !== NULL) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '.');
                redirect('haynelimits');
                return;
            }
        }

        try {
            $this->hayne_credit_exemption_model->saveOfficialSummonsPolicy((int) $leaveTypeId, $enabled);
            $this->session->set_flashdata(
                'msg',
                $enabled
                    ? 'Wezwanie organu: kontrola salda została wyłączona dla wskazanego rodzaju nieobecności.'
                    : 'Wyjątek salda dla wezwania organu został wyłączony.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE official-summons policy save failed: ' . $exception->getMessage());
            $this->session->set_flashdata(
                'msg',
                'Nie udało się zapisać ustawień wezwania organu: ' . $exception->getMessage()
            );
        }

        redirect('haynelimits');
    }

    public function saveChildcareAllocation(): void
    {
        $employeeId = filter_var($this->input->post('employee_id', TRUE), FILTER_VALIDATE_INT);
        $year = filter_var($this->input->post('year', TRUE), FILTER_VALIDATE_INT);
        $days = filter_var($this->input->post('childcare_days', TRUE), FILTER_VALIDATE_INT);
        $currentYear = (int) date('Y');

        if (
            $employeeId === FALSE || $employeeId <= 0 ||
            $year === FALSE || $year < ($currentYear - 5) || $year > ($currentYear + 1) ||
            $days === FALSE || $days < 0 || $days > 2
        ) {
            $this->session->set_flashdata('msg', 'Nieprawidłowy limit opieki nad dzieckiem.');
            redirect('haynelimits');
            return;
        }

        $this->load->model('users_model');
        $employee = $this->users_model->getUsers((int) $employeeId);
        if (empty($employee) || (int) $employee['active'] !== 1) {
            $this->session->set_flashdata('msg', 'Nie znaleziono aktywnego pracownika.');
            redirect('haynelimits?year=' . (int) $year);
            return;
        }

        try {
            $this->hayne_childcare_model->saveAllocation((int) $employeeId, (int) $year, (int) $days);
            $this->session->set_flashdata(
                'msg',
                'Opieka nad dzieckiem: zapisano limit ' . (int) $days . ' dni na ' . (int) $year . ' rok.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE childcare allocation save failed: ' . $exception->getMessage());
            $this->session->set_flashdata('msg', $exception->getMessage());
        }

        redirect('haynelimits?year=' . (int) $year);
    }

    private function activeStatutoryPolicyForType(int $leaveTypeId, ?string $excludePolicy = NULL): ?string
    {
        $policies = [
            'caregiver' => [
                'policy' => $this->hayne_caregiver_leave_model->getPolicy(),
                'label' => 'urlop opiekuńczy',
            ],
            'force_majeure' => [
                'policy' => $this->hayne_force_majeure_model->getPolicy(),
                'label' => 'siła wyższa',
            ],
            'childcare' => [
                'policy' => $this->hayne_childcare_model->getPolicy(),
                'label' => 'opieka nad dzieckiem do 14 lat',
            ],
            'occasion' => [
                'policy' => $this->hayne_occasion_leave_model->getPolicy(),
                'label' => 'urlop okolicznościowy',
            ],
            'holiday_compensation' => [
                'policy' => $this->hayne_holiday_compensation_model->getPolicy(),
                'label' => 'dzień wolny za święto',
            ],
            'official_summons' => [
                'policy' => $this->hayne_credit_exemption_model->getOfficialSummonsPolicy(),
                'label' => 'wezwanie sądu / urzędu / innego organu',
            ],
        ];

        foreach ($policies as $code => $item) {
            if ($excludePolicy === $code) {
                continue;
            }
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

    private function isVacationTypeInUse(int $leaveTypeId): bool
    {
        if (!$this->db->table_exists('hayne_leave_profiles')) {
            return FALSE;
        }

        return $this->db
            ->where('vacation_type_id', $leaveTypeId)
            ->count_all_results('hayne_leave_profiles') > 0;
    }

    private function assertAccess(): void
    {
        if (!$this->is_hr && !$this->is_admin) {
            show_error('Forbidden', 403);
        }
    }
}
