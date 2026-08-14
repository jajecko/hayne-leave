<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

/**
 * HAYNE manager-facing review surface.
 *
 * This controller is intentionally read-only. Workflow mutations continue to
 * use Jorani's native Requests accept/reject endpoints and authorization.
 */
class Hayneapprovals extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        setUserContext($this);
        $this->load->model('leaves_model');
        $this->lang->load('requests', $this->language);
        $this->lang->load('global', $this->language);
    }

    public function review(int $id): void
    {
        $this->auth->checkIfOperationIsAllowed('list_requests');
        $this->load->model('users_model');
        $this->load->model('delegations_model');
        $this->load->model('status_model');
        $this->load->helper('form');

        $data = getUserContext($this);
        $data['leave'] = $this->leaves_model->getLeaveWithComments($id);
        if (empty($data['leave'])) {
            redirect('notfound');
        }

        $employee = $this->users_model->getUsers($data['leave']['employee']);
        $isDelegate = $this->delegations_model->isDelegateOfManager($this->user_id, $employee['manager']);
        if (($this->user_id != $employee['manager']) && !$this->is_hr && !$isDelegate) {
            log_message('error', 'User #' . $this->user_id . ' illegally tried to review leave #' . $id);
            $this->session->set_flashdata('msg', lang('requests_accept_flash_msg_error'));
            redirect('requests');
        }

        $data['name'] = $employee['firstname'] . ' ' . $employee['lastname'];
        $data['mandatoryCommentOnReject'] = $this->config->item('mandatory_comment_on_reject') === TRUE;
        $data['title'] = 'Wniosek do akceptacji';

        if (
            isset($data['leave']['comments']) &&
            is_object($data['leave']['comments']) &&
            isset($data['leave']['comments']->comments) &&
            is_array($data['leave']['comments']->comments)
        ) {
            foreach ($data['leave']['comments']->comments as $commentsItem) {
                if ($commentsItem->type === 'comment') {
                    $commentsItem->author_name = $this->users_model->getName($commentsItem->author);
                } elseif ($commentsItem->type === 'change') {
                    $commentsItem->status_label = lang($this->status_model->getName($commentsItem->status_number));
                }
            }
        }

        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('requests/review', $data);
        $this->load->view('templates/footer');
    }
}
