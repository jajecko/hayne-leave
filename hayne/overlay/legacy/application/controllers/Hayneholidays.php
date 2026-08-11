<?php
/**
 * HAYNE administration for replacement days off caused by public holidays.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayneholidays extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        setUserContext($this);
        $this->assertAccess();
        $this->load->helper('form');
        $this->load->model('hayne_holiday_compensation_model');
        $this->load->model('users_model');
        $this->load->model('types_model');
    }

    public function index(): void
    {
        try {
            $managedTypeId = $this->hayne_holiday_compensation_model->ensureManagedLeaveType();
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE holiday managed type ensure failed: ' . $exception->getMessage());
            show_error('Nie udało się przygotować rodzaju „Dzień wolny za święto”.', 500);
            return;
        }

        $data = getUserContext($this);
        $data['employees'] = $this->users_model->getUsers();
        $data['policy'] = $this->hayne_holiday_compensation_model->getPolicy();
        $data['managed_type_id'] = $managedTypeId;
        $data['managed_type'] = $this->types_model->getTypes($managedTypeId);
        $data['grants'] = $this->hayne_holiday_compensation_model->getGrants();
        $data['title'] = 'Dzień wolny za święto';
        $data['help'] = '';
        $data['flash_partial_view'] = $this->load->view('templates/flash', $data, TRUE);
        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('hayneholidays/index', $data);
        $this->load->view('templates/footer');
    }

    public function savePolicy(): void
    {
        try {
            $managedTypeId = $this->hayne_holiday_compensation_model->ensureManagedLeaveType();
        } catch (Throwable $exception) {
            $this->session->set_flashdata('msg', $exception->getMessage());
            redirect('hayneholidays');
            return;
        }

        $postedTypeId = filter_var(
            $this->input->post('holiday_compensation_type_id', TRUE),
            FILTER_VALIDATE_INT
        );
        $enabled = $this->input->post('holiday_compensation_enabled', TRUE) === '1';
        if ($postedTypeId === FALSE || (int) $postedTypeId !== $managedTypeId) {
            $this->session->set_flashdata('msg', 'Nieprawidłowy rodzaj dnia wolnego za święto.');
            redirect('hayneholidays');
            return;
        }

        if ($enabled) {
            if ($this->isVacationTypeInUse($managedTypeId)) {
                $this->session->set_flashdata('msg', 'Typ „Dzień wolny za święto” jest już używany jako urlop wypoczynkowy.');
                redirect('hayneholidays');
                return;
            }
            $collision = $this->otherActivePolicyForType($managedTypeId);
            if ($collision !== NULL) {
                $this->session->set_flashdata('msg', 'Ten rodzaj nieobecności jest już używany dla polityki: ' . $collision . '.');
                redirect('hayneholidays');
                return;
            }
        }

        try {
            $this->hayne_holiday_compensation_model->savePolicy($managedTypeId, $enabled);
            $this->session->set_flashdata(
                'msg',
                $enabled
                    ? 'Obsługa dnia wolnego za święto została włączona.'
                    : 'Obsługa dnia wolnego za święto została wyłączona.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE holiday policy save failed: ' . $exception->getMessage());
            $this->session->set_flashdata('msg', $exception->getMessage());
        }
        redirect('hayneholidays');
    }

    public function saveGrant(): void
    {
        $employeeId = filter_var($this->input->post('employee_id', TRUE), FILTER_VALIDATE_INT);
        $sourceHolidayDate = trim((string) $this->input->post('source_holiday_date', TRUE));
        $periodStart = trim((string) $this->input->post('period_start', TRUE));
        $periodEnd = trim((string) $this->input->post('period_end', TRUE));

        if ($employeeId === FALSE || $employeeId <= 0) {
            $this->session->set_flashdata('msg', 'Wybierz prawidłowego pracownika.');
            redirect('hayneholidays');
            return;
        }
        $employee = $this->users_model->getUsers((int) $employeeId);
        if (empty($employee) || (int) $employee['active'] !== 1) {
            $this->session->set_flashdata('msg', 'Nie znaleziono aktywnego pracownika.');
            redirect('hayneholidays');
            return;
        }

        try {
            $this->hayne_holiday_compensation_model->saveGrant(
                (int) $employeeId,
                $sourceHolidayDate,
                $periodStart,
                $periodEnd
            );
            $this->session->set_flashdata(
                'msg',
                'Przyznano 1 dzień wolny za święto ' . $sourceHolidayDate .
                ' do wykorzystania w okresie ' . $periodStart . ' – ' . $periodEnd . '.'
            );
        } catch (Throwable $exception) {
            log_message('error', 'HAYNE holiday grant save failed: ' . $exception->getMessage());
            $this->session->set_flashdata('msg', $exception->getMessage());
        }
        redirect('hayneholidays');
    }

    private function otherActivePolicyForType(int $leaveTypeId): ?string
    {
        $rows = $this->db
            ->where('leave_type_id', $leaveTypeId)
            ->where('enabled', 1)
            ->where('policy_code !=', Hayne_holiday_compensation_model::POLICY_CODE)
            ->get('hayne_statutory_leave_policies')
            ->result_array();
        if (empty($rows)) {
            return NULL;
        }
        $labels = [
            'caregiver' => 'urlop opiekuńczy',
            'force_majeure' => 'siła wyższa',
            'childcare' => 'opieka nad dzieckiem do 14 lat',
            'occasion' => 'urlop okolicznościowy',
        ];
        $code = (string) $rows[0]['policy_code'];
        return $labels[$code] ?? $code;
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
