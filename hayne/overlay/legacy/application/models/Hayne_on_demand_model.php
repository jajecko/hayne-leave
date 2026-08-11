<?php
/**
 * HAYNE "urlop na żądanie" policy.
 *
 * On-demand leave is metadata on an ordinary vacation leave request. It never
 * creates a separate entitlement: the underlying Jorani leave type remains the
 * employee's configured vacation type, so the same balance and FIFO pools are
 * consumed.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_on_demand_model extends CI_Model
{
    private const META_TABLE = 'hayne_leave_request_meta';
    public const ANNUAL_LIMIT_DAYS = 4;

    public function __construct()
    {
        $this->load->model('hayne_leave_policy_model');
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->db->table_exists(self::META_TABLE)) {
            return;
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `hayne_leave_request_meta` (
                `leave_id` int(11) NOT NULL,
                `on_demand` tinyint(1) NOT NULL DEFAULT 0,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`leave_id`),
                KEY `on_demand` (`on_demand`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function isOnDemand(int $leaveId): bool
    {
        if ($leaveId <= 0) {
            return FALSE;
        }

        return $this->db
            ->where('leave_id', $leaveId)
            ->where('on_demand', 1)
            ->count_all_results(self::META_TABLE) > 0;
    }

    public function setOnDemand(int $leaveId, bool $onDemand): void
    {
        if ($leaveId <= 0) {
            throw new InvalidArgumentException('Leave id must be positive.');
        }

        if (!$onDemand) {
            $this->db->where('leave_id', $leaveId)->delete(self::META_TABLE);
            return;
        }

        $existing = $this->db
            ->where('leave_id', $leaveId)
            ->count_all_results(self::META_TABLE) > 0;

        if ($existing) {
            $this->db->where('leave_id', $leaveId)->update(self::META_TABLE, ['on_demand' => 1]);
        } else {
            $this->db->insert(self::META_TABLE, [
                'leave_id' => $leaveId,
                'on_demand' => 1,
            ]);
        }
    }

    /**
     * Lock the employee's HAYNE profile inside a caller-owned transaction.
     * This serializes simultaneous on-demand submissions for one employee.
     */
    public function lockEmployeeProfile(int $employeeId): void
    {
        $profile = $this->hayne_leave_policy_model->getProfile($employeeId);
        if (empty($profile)) {
            throw new InvalidArgumentException('Brak skonfigurowanej puli urlopu wypoczynkowego.');
        }

        $this->db->query(
            'SELECT employee_id FROM hayne_leave_profiles WHERE employee_id = ? FOR UPDATE',
            [$employeeId]
        );
    }

    /**
     * Requested, accepted and cancellation-pending requests reserve the annual
     * four-day sublimit. Plans do not reserve it until they are submitted.
     */
    public function getUsedDays(int $employeeId, int $year, ?int $excludeLeaveId = NULL): float
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $this->db->select('COALESCE(SUM(leaves.duration), 0) AS used', FALSE);
        $this->db->from('leaves');
        $this->db->join(self::META_TABLE, self::META_TABLE . '.leave_id = leaves.id');
        $this->db->where(self::META_TABLE . '.on_demand', 1);
        $this->db->where('leaves.employee', $employeeId);
        $this->db->where_in('leaves.status', [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION]);
        $this->db->where('leaves.startdate >=', $startDate);
        $this->db->where('leaves.enddate <=', $endDate);
        if ($excludeLeaveId !== NULL && $excludeLeaveId > 0) {
            $this->db->where('leaves.id !=', $excludeLeaveId);
        }
        $row = $this->db->get()->row_array();

        return max(0.0, (float) ($row['used'] ?? 0));
    }

    /**
     * @return array<string, mixed>
     */
    public function getSummary(int $employeeId, int $year, ?int $excludeLeaveId = NULL): array
    {
        $used = $this->getUsedDays($employeeId, $year, $excludeLeaveId);
        return [
            'limit' => self::ANNUAL_LIMIT_DAYS,
            'used' => $used,
            'remaining' => max(0.0, self::ANNUAL_LIMIT_DAYS - $used),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormState(int $employeeId, ?int $leaveId = NULL): array
    {
        $profile = $this->hayne_leave_policy_model->getProfile($employeeId);
        $year = (int) date('Y');
        $current = $leaveId !== NULL && $this->isOnDemand($leaveId);
        $summary = $this->getSummary($employeeId, $year, $current ? $leaveId : NULL);

        return [
            'enabled' => !empty($profile),
            'vacation_type_id' => empty($profile) ? 0 : (int) $profile['vacation_type_id'],
            'year' => $year,
            'limit' => self::ANNUAL_LIMIT_DAYS,
            'used' => $summary['used'],
            'remaining' => $summary['remaining'],
            'current' => $current,
        ];
    }

    /**
     * Validate an on-demand request. The caller should hold the employee
     * profile row lock while creating/updating a reserving request.
     */
    public function assertRequestAllowed(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $duration,
        int $status,
        ?int $excludeLeaveId = NULL
    ): void {
        $profile = $this->hayne_leave_policy_model->getProfile($employeeId);
        if (empty($profile)) {
            throw new InvalidArgumentException('Brak skonfigurowanej puli urlopu wypoczynkowego.');
        }
        if ((int) $profile['vacation_type_id'] !== $leaveTypeId) {
            throw new InvalidArgumentException('Urlop na żądanie musi korzystać z puli urlopu wypoczynkowego.');
        }
        if ($duration <= 0 || floor($duration) != $duration) {
            throw new InvalidArgumentException('Urlop na żądanie można składać wyłącznie na pełne dni.');
        }

        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        if ($startYear <= 0 || $startYear !== $endYear) {
            throw new InvalidArgumentException('Jeden wniosek na żądanie nie może obejmować dwóch lat kalendarzowych.');
        }
        if ($duration > self::ANNUAL_LIMIT_DAYS) {
            throw new InvalidArgumentException('Urlop na żądanie ma limit 4 dni w roku kalendarzowym.');
        }

        if (!in_array($status, [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION], TRUE)) {
            return;
        }

        $used = $this->getUsedDays($employeeId, $startYear, $excludeLeaveId);
        if (($used + $duration) > self::ANNUAL_LIMIT_DAYS) {
            $remaining = max(0, self::ANNUAL_LIMIT_DAYS - $used);
            throw new InvalidArgumentException(
                'Przekroczony limit urlopu na żądanie. Pozostało: ' . $remaining . ' dni.'
            );
        }
    }

    /**
     * Replace the displayed type name for HAYNE on-demand requests without
     * changing their underlying Jorani vacation type.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public function decorateRows(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $ids = [];
        foreach ($rows as $row) {
            if (isset($row['id'])) {
                $ids[] = (int) $row['id'];
            }
        }
        $ids = array_values(array_unique(array_filter($ids, static fn(int $id): bool => $id > 0)));
        if (empty($ids)) {
            return $rows;
        }

        $metaRows = $this->db
            ->select('leave_id')
            ->where('on_demand', 1)
            ->where_in('leave_id', $ids)
            ->get(self::META_TABLE)
            ->result_array();
        $onDemandIds = [];
        foreach ($metaRows as $metaRow) {
            $onDemandIds[(int) $metaRow['leave_id']] = TRUE;
        }

        foreach ($rows as &$row) {
            $leaveId = isset($row['id']) ? (int) $row['id'] : 0;
            if (isset($onDemandIds[$leaveId])) {
                $row['type_name'] = 'Urlop na żądanie';
                $row['hayne_on_demand'] = TRUE;
            } else {
                $row['hayne_on_demand'] = FALSE;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $leave
     * @return array<string, mixed>
     */
    public function decorateLeave(array $leave): array
    {
        if (!empty($leave) && isset($leave['id']) && $this->isOnDemand((int) $leave['id'])) {
            $leave['type_name'] = 'Urlop na żądanie';
            $leave['hayne_on_demand'] = TRUE;
        } else {
            $leave['hayne_on_demand'] = FALSE;
        }
        return $leave;
    }
}
