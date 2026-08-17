<?php
/**
 * HAYNE adapter for leave approval routing.
 *
 * The central leave-type registry is authoritative for workflow_mode. Policy
 * decisions are made by leave_type_id only; display names are never used.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_leave_workflow_model extends CI_Model
{
    private const WORKFLOW_APPROVAL = 'APPROVAL';
    private const WORKFLOW_HR = 'HR';
    private const WORKFLOW_NONE = 'NONE';
    private const HR_ROLE_BIT = 8;

    public function __construct()
    {
        $this->load->model('Hayne_leave_type_registry_model', 'hayne_leave_type_registry_model');
    }

    public function getWorkflowModeForType(int $leaveTypeId): ?string
    {
        if ($leaveTypeId <= 0) {
            return NULL;
        }

        $policy = $this->hayne_leave_type_registry_model->getPolicyForType($leaveTypeId);
        if (empty($policy) || (int) ($policy['enabled'] ?? 0) !== 1) {
            return NULL;
        }

        $mode = strtoupper(trim((string) ($policy['workflow_mode'] ?? '')));
        if (!in_array($mode, [self::WORKFLOW_APPROVAL, self::WORKFLOW_HR, self::WORKFLOW_NONE], TRUE)) {
            log_message('error', 'Unknown HAYNE workflow_mode for leave type #' . $leaveTypeId . ': ' . $mode);
            return NULL;
        }

        return $mode;
    }

    public function isHrWorkflowType(int $leaveTypeId): bool
    {
        return $this->getWorkflowModeForType($leaveTypeId) === self::WORKFLOW_HR;
    }

    /**
     * HR-routed pending work must always have at least one active HR account
     * with an e-mail address. Standard workflows keep upstream Jorani behavior.
     */
    public function hasRequiredNotificationRecipient(int $leaveTypeId): bool
    {
        if (!$this->isHrWorkflowType($leaveTypeId)) {
            return TRUE;
        }

        return !empty($this->getActiveHrRecipients());
    }

    /**
     * Keep upstream authorization for APPROVAL/missing policies and make HR
     * routing explicit. NONE has no approval path.
     */
    public function canActorDecide(
        int $leaveTypeId,
        bool $isHr,
        bool $isAdmin,
        bool $isLineManager,
        bool $isDelegate
    ): bool {
        $mode = $this->getWorkflowModeForType($leaveTypeId);

        if ($mode === self::WORKFLOW_HR) {
            return $isHr || $isAdmin;
        }
        if ($mode === self::WORKFLOW_NONE) {
            return FALSE;
        }

        // Preserve native Jorani behavior for APPROVAL and for installations
        // where the central registry has no applicable row.
        return $isLineManager || $isHr || $isDelegate;
    }

    /**
     * Resolve the primary notification recipient. HR workflow deliberately
     * ignores users.manager; standard workflow keeps the native manager.
     *
     * @param array<string, mixed> $leave
     * @param array<string, mixed> $employee
     * @return array<string, mixed>|null
     */
    public function getPrimaryNotificationRecipient(array $leave, array $employee): ?array
    {
        if ($this->isHrWorkflowType((int) ($leave['type'] ?? 0))) {
            $recipients = $this->getActiveHrRecipients();
            return empty($recipients) ? NULL : $recipients[0];
        }

        $managerId = (int) ($employee['manager'] ?? 0);
        if ($managerId <= 0) {
            return NULL;
        }

        $row = $this->db->where('id', $managerId)->get('users')->row_array();
        return empty($row) ? NULL : $row;
    }

    /**
     * Additional HR approvers copied on HR-workflow notifications. Delegates
     * are intentionally not used for HR workflow.
     *
     * @return array<int, string>
     */
    public function getAdditionalHrRecipientEmails(int $primaryUserId): array
    {
        $emails = [];
        foreach ($this->getActiveHrRecipients() as $recipient) {
            if ((int) $recipient['id'] === $primaryUserId) {
                continue;
            }
            $email = trim((string) ($recipient['email'] ?? ''));
            if ($email !== '') {
                $emails[] = $email;
            }
        }
        return array_values(array_unique($emails));
    }

    /**
     * Remove HR-workflow requests from a normal manager queue. HR/admin sees
     * the global HR queue merged with any ordinary requests they can already
     * review through native Jorani.
     *
     * @param array<int, array<string, mixed>> $managerRequests
     * @return array<int, array<string, mixed>>
     */
    public function filterAndMergeApproverRequests(array $managerRequests, bool $canReviewHr, bool $showAll): array
    {
        $merged = [];
        foreach ($managerRequests as $request) {
            if ($this->isHrWorkflowType((int) ($request['type'] ?? 0))) {
                continue;
            }
            $key = (int) ($request['leave_id'] ?? $request['id'] ?? 0);
            $merged[$key] = $request;
        }

        if ($canReviewHr) {
            foreach ($this->getHrWorkflowRequests($showAll) as $request) {
                $key = (int) ($request['leave_id'] ?? $request['id'] ?? 0);
                $merged[$key] = $request;
            }
        }

        $rows = array_values($merged);
        usort($rows, static function (array $left, array $right): int {
            return strcmp((string) ($right['startdate'] ?? ''), (string) ($left['startdate'] ?? ''));
        });
        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    private function getActiveHrRecipients(): array
    {
        return $this->db
            ->where('active', 1)
            ->where('(role & ' . self::HR_ROLE_BIT . ') = ' . self::HR_ROLE_BIT, NULL, FALSE)
            ->where('email IS NOT NULL', NULL, FALSE)
            ->where("TRIM(email) <> ''", NULL, FALSE)
            ->order_by('id', 'asc')
            ->get('users')
            ->result_array();
    }

    /** @return array<int, array<string, mixed>> */
    private function getHrWorkflowRequests(bool $showAll): array
    {
        $this->db->select('leaves.id as leave_id, users.*, leaves.*, types.name as type_label');
        $this->db->select('status.name as status_name, types.name as type_name');
        $this->db->from('leaves');
        $this->db->join('status', 'leaves.status = status.id');
        $this->db->join('types', 'leaves.type = types.id');
        $this->db->join('users', 'users.id = leaves.employee');
        $this->db->join('hayne_leave_type_registry', 'hayne_leave_type_registry.leave_type_id = leaves.type');
        $this->db->where('hayne_leave_type_registry.enabled', 1);
        $this->db->where('hayne_leave_type_registry.workflow_mode', self::WORKFLOW_HR);
        if (!$showAll) {
            $this->db->where_in('leaves.status', [LMS_REQUESTED, LMS_CANCELLATION]);
        }
        $this->db->order_by('leaves.startdate', 'desc');
        $rows = $this->db->get()->result_array();

        // Requests/index can run with history enabled. Keep the row shape safe
        // without duplicating the expensive history query in this MVP slice.
        foreach ($rows as &$row) {
            if (!array_key_exists('change_date', $row)) {
                $row['change_date'] = NULL;
            }
            if (!array_key_exists('request_date', $row)) {
                $row['request_date'] = NULL;
            }
        }
        unset($row);

        return $rows;
    }
}
