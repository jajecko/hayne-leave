<?php
/**
 * HAYNE administration surface for persistent annual vacation settings.
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

    private function assertAccess(): void
    {
        if (!$this->is_hr && !$this->is_admin) {
            show_error('Forbidden', 403);
        }
    }
}
