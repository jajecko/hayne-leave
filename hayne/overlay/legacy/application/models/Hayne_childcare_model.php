<?php
/**
 * HAYNE child-care leave policy (Polish Labour Code art. 188).
 *
 * Product scope is whole-day only. A global policy maps the statutory absence
 * to one dedicated Jorani leave type, while HR assigns an employee-specific
 * annual allowance of 0, 1 or 2 days. This deliberately avoids storing child
 * personal data and supports a one-day split of the statutory entitlement.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_childcare_model extends CI_Model
{
    public const POLICY_CODE = 'childcare';
    public const MAX_ANNUAL_DAYS = 2;

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';
    private const ALLOCATION_TABLE = 'hayne_childcare_year_allocations';
    private const ENTITLEMENT_PREFIX = '[HAYNE_STATUTORY|childcare|';

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if (!$this->db->table_exists(self::POLICY_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_statutory_leave_policies` (
                    `policy_code` varchar(32) NOT NULL,
                    `leave_type_id` int(11) NOT NULL,
                    `enabled` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`policy_code`),
                    KEY `leave_type_id` (`leave_type_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->db->table_exists(self::ALLOCATION_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_childcare_year_allocations` (
                    `employee_id` int(11) NOT NULL,
                    `year` smallint(4) NOT NULL,
                    `granted_days` tinyint(1) NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`employee_id`, `year`),
                    KEY `year` (`year`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    public function getPolicy(): ?array
    {
        $row = $this->db
            ->where('policy_code', self::POLICY_CODE)
            ->get(self::POLICY_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    public function savePolicy(int $leaveTypeId, bool $enabled): void
    {
        if ($leaveTypeId <= 0) {
            throw new InvalidArgumentException('Nieprawidłowy rodzaj opieki nad dzieckiem do 14 lat.');
        }

        $existing = $this->getPolicy();
        if (!empty($existing) && (int) $existing['leave_type_id'] !== $leaveTypeId) {
            if ($this->hasManagedEntitlements() || $this->hasRecordedRequests((int) $existing['leave_type_id'])) {
                throw new InvalidArgumentException(
                    'Nie można zmienić rodzaju opieki nad dzieckiem po utworzeniu pul lub zapisaniu wniosków.'
                );
            }
        }

        if (!$enabled && !empty($existing) && $this->hasRecordedRequests((int) $existing['leave_type_id'])) {
            throw new InvalidArgumentException(
                'Nie można wyłączyć opieki nad dzieckiem po zapisaniu wniosków.'
            );
        }

        $data = [
            'policy_code' => self::POLICY_CODE,
            'leave_type_id' => $leaveTypeId,
            'enabled' => $enabled ? 1 : 0,
        ];

        if (empty($existing)) {
            $this->db->insert(self::POLICY_TABLE, $data);
        } else {
            $this->db
                ->where('policy_code', self::POLICY_CODE)
                ->update(self::POLICY_TABLE, $data);
        }

        if (!$enabled) {
            $this->deleteManagedEntitlements();
        }
    }

    public function isEnabled(): bool
    {
        $policy = $this->getPolicy();
        return !empty($policy) && (int) $policy['enabled'] === 1;
    }

    public function isChildcareType(int $leaveTypeId): bool
    {
        $policy = $this->getPolicy();
        return !empty($policy)
            && (int) $policy['enabled'] === 1
            && (int) $policy['leave_type_id'] === $leaveTypeId;
    }

    public function isChildcareLeave(int $leaveId): bool
    {
        $policy = $this->getPolicy();
        if (empty($policy)) {
            return FALSE;
        }

        return $this->db
            ->where('id', $leaveId)
            ->where('type', (int) $policy['leave_type_id'])
            ->count_all_results('leaves') > 0;
    }

    public function getAllocation(int $employeeId, int $year): ?array
    {
        $row = $this->db
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->get(self::ALLOCATION_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    /** @return array<int, int> */
    public function getAllocationMap(int $year): array
    {
        $rows = $this->db
            ->where('year', $year)
            ->get(self::ALLOCATION_TABLE)
            ->result_array();

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['employee_id']] = (int) $row['granted_days'];
        }
        return $map;
    }

    public function saveAllocation(int $employeeId, int $year, int $grantedDays): void
    {
        if ($employeeId <= 0 || $year < 1970 || $year > 2100) {
            throw new InvalidArgumentException('Nieprawidłowy pracownik lub rok opieki nad dzieckiem.');
        }
        if ($grantedDays < 0 || $grantedDays > self::MAX_ANNUAL_DAYS) {
            throw new InvalidArgumentException('Limit opieki nad dzieckiem może wynosić 0, 1 albo 2 dni.');
        }

        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Najpierw skonfiguruj rodzaj nieobecności dla opieki nad dzieckiem.');
        }

        $leaveTypeId = (int) $policy['leave_type_id'];
        $this->db->trans_begin();
        try {
            $this->lockExistingEntitlement($employeeId, $leaveTypeId, $year);
            $used = $this->getReservedUsage($employeeId, $leaveTypeId, $year, NULL);
            if ($grantedDays < $used) {
                throw new InvalidArgumentException(
                    'Nie można ustawić limitu niższego niż już wykorzystane lub zarezerwowane ' .
                    $this->formatDays($used) . ' dni.'
                );
            }

            if ($grantedDays === 0) {
                $this->db
                    ->where('employee_id', $employeeId)
                    ->where('year', $year)
                    ->delete(self::ALLOCATION_TABLE);
                $this->deleteManagedEntitlement($employeeId, $year);
            } else {
                $existing = $this->getAllocation($employeeId, $year);
                $data = [
                    'employee_id' => $employeeId,
                    'year' => $year,
                    'granted_days' => $grantedDays,
                ];
                if (empty($existing)) {
                    $this->db->insert(self::ALLOCATION_TABLE, $data);
                } else {
                    $this->db
                        ->where('employee_id', $employeeId)
                        ->where('year', $year)
                        ->update(self::ALLOCATION_TABLE, ['granted_days' => $grantedDays]);
                }
                $this->ensureYear($employeeId, $year);
            }

            if ($this->db->trans_status() === FALSE) {
                throw new RuntimeException('Nie udało się zapisać rocznego limitu opieki nad dzieckiem.');
            }
            $this->db->trans_commit();
        } catch (Throwable $exception) {
            $this->db->trans_rollback();
            throw $exception;
        }
    }

    public function ensureCurrentYear(int $employeeId): void
    {
        $this->ensureYear($employeeId, (int) date('Y'));
    }

    public function ensureConfiguredYearForAll(int $year): void
    {
        $rows = $this->db
            ->where('year', $year)
            ->where('granted_days >', 0)
            ->get(self::ALLOCATION_TABLE)
            ->result_array();
        foreach ($rows as $row) {
            $this->ensureYear((int) $row['employee_id'], $year);
        }
    }

    /**
     * Idempotently mirrors the employee/year allocation into Jorani entitleddays.
     * There is no carry-over path for this statutory pool.
     */
    public function ensureYear(int $employeeId, int $year): void
    {
        if ($employeeId <= 0 || $year < 1970 || $year > 2100) {
            return;
        }

        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            return;
        }

        $allocation = $this->getAllocation($employeeId, $year);
        if (empty($allocation) || (int) $allocation['granted_days'] <= 0) {
            $this->deleteManagedEntitlement($employeeId, $year);
            return;
        }

        $leaveTypeId = (int) $policy['leave_type_id'];
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
        $description = $this->entitlementMarker($year);
        $grantedDays = (int) $allocation['granted_days'];

        $existing = $this->db
            ->where('employee', $employeeId)
            ->where('type', $leaveTypeId)
            ->where('startdate', $startDate)
            ->where('enddate', $endDate)
            ->where('description', $description)
            ->get('entitleddays')
            ->row_array();

        $data = [
            'employee' => $employeeId,
            'startdate' => $startDate,
            'enddate' => $endDate,
            'days' => $grantedDays,
            'type' => $leaveTypeId,
            'description' => $description,
        ];

        if (empty($existing)) {
            $this->db->insert('entitleddays', $data);
        } elseif ((int) $existing['days'] !== $grantedDays) {
            $this->db
                ->where('id', (int) $existing['id'])
                ->update('entitleddays', ['days' => $grantedDays]);
        }
    }

    public function lockEmployeeYear(int $employeeId, int $year): void
    {
        $allocation = $this->getAllocation($employeeId, $year);
        if (empty($allocation) || (int) $allocation['granted_days'] <= 0) {
            throw new InvalidArgumentException(
                'Brak przyznanego limitu opieki nad dzieckiem do 14 lat na ' . $year . ' rok.'
            );
        }

        $this->ensureYear($employeeId, $year);
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Opieka nad dzieckiem do 14 lat nie jest skonfigurowana.');
        }

        $row = $this->db->query(
            'SELECT id FROM entitleddays WHERE employee = ? AND type = ? AND startdate = ? AND enddate = ? AND description = ? FOR UPDATE',
            [
                $employeeId,
                (int) $policy['leave_type_id'],
                sprintf('%04d-01-01', $year),
                sprintf('%04d-12-31', $year),
                $this->entitlementMarker($year),
            ]
        )->row_array();

        if (empty($row)) {
            throw new RuntimeException('Nie udało się zablokować rocznej puli opieki nad dzieckiem.');
        }
    }

    public function getFormState(int $employeeId, ?int $leaveId = NULL): array
    {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            return ['enabled' => FALSE];
        }

        $year = (int) date('Y');
        if ($leaveId !== NULL) {
            $leave = $this->db
                ->select('startdate')
                ->where('id', $leaveId)
                ->get('leaves')
                ->row_array();
            if (!empty($leave['startdate'])) {
                $year = (int) substr((string) $leave['startdate'], 0, 4);
            }
        }

        $allocation = $this->getAllocation($employeeId, $year);
        $limit = empty($allocation) ? 0 : (int) $allocation['granted_days'];
        if ($limit > 0) {
            $this->ensureYear($employeeId, $year);
        }
        $used = $this->getReservedUsage($employeeId, (int) $policy['leave_type_id'], $year, $leaveId);

        return [
            'enabled' => TRUE,
            'allocated' => $limit > 0,
            'year' => $year,
            'leave_type_id' => (int) $policy['leave_type_id'],
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0.0, $limit - $used),
        ];
    }

    public function getSummary(int $employeeId, int $year): ?array
    {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            return NULL;
        }

        $allocation = $this->getAllocation($employeeId, $year);
        if (empty($allocation) || (int) $allocation['granted_days'] <= 0) {
            return NULL;
        }

        $this->ensureYear($employeeId, $year);
        $limit = (int) $allocation['granted_days'];
        $used = $this->getReservedUsage($employeeId, (int) $policy['leave_type_id'], $year, NULL);

        return [
            'year' => $year,
            'limit' => $limit,
            'used' => $used,
            'remaining' => max(0.0, $limit - $used),
            'leave_type_id' => (int) $policy['leave_type_id'],
        ];
    }

    public function assertRequestAllowed(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $duration,
        int $status,
        ?int $excludeLeaveId = NULL
    ): void {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Opieka nad dzieckiem do 14 lat nie jest skonfigurowana.');
        }
        if ((int) $policy['leave_type_id'] !== $leaveTypeId) {
            throw new InvalidArgumentException('Wniosek nie używa skonfigurowanego rodzaju opieki nad dzieckiem.');
        }
        if ($duration <= 0 || floor($duration) != $duration) {
            throw new InvalidArgumentException('Opieka nad dzieckiem jest obsługiwana w HAYNE wyłącznie w pełnych dniach.');
        }
        if (!$this->isIsoDate($startDate) || !$this->isIsoDate($endDate) || $startDate > $endDate) {
            throw new InvalidArgumentException('Nieprawidłowy zakres dat opieki nad dzieckiem.');
        }

        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        if ($startYear !== $endYear) {
            throw new InvalidArgumentException('Jeden wniosek o opiekę nad dzieckiem nie może obejmować dwóch lat kalendarzowych.');
        }

        $allocation = $this->getAllocation($employeeId, $startYear);
        $limit = empty($allocation) ? 0 : (int) $allocation['granted_days'];
        if ($limit <= 0) {
            throw new InvalidArgumentException(
                'Brak przyznanego limitu opieki nad dzieckiem do 14 lat na ' . $startYear . ' rok.'
            );
        }
        if ($duration > $limit) {
            throw new InvalidArgumentException(
                'Wniosek przekracza przyznany limit opieki nad dzieckiem: ' . $limit . ' dni.'
            );
        }

        $this->ensureYear($employeeId, $startYear);
        if ($this->statusReservesLimit($status)) {
            $used = $this->getReservedUsage($employeeId, $leaveTypeId, $startYear, $excludeLeaveId);
            $remaining = max(0.0, $limit - $used);
            if ($duration > $remaining) {
                throw new InvalidArgumentException(
                    'Przekroczony limit opieki nad dzieckiem. Pozostało: ' .
                    $this->formatDays($remaining) . ' dni.'
                );
            }
        }
    }

    private function getReservedUsage(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        ?int $excludeLeaveId
    ): float {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $this->db->select('COALESCE(SUM(duration), 0) AS used', FALSE);
        $this->db->from('leaves');
        $this->db->where('employee', $employeeId);
        $this->db->where('type', $leaveTypeId);
        $this->db->where_in('status', [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION]);
        $this->db->where('startdate >=', $startDate);
        $this->db->where('enddate <=', $endDate);
        if ($excludeLeaveId !== NULL) {
            $this->db->where('id !=', $excludeLeaveId);
        }
        $row = $this->db->get()->row_array();

        return max(0.0, (float) ($row['used'] ?? 0));
    }

    private function statusReservesLimit(int $status): bool
    {
        return in_array($status, [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION], TRUE);
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== FALSE && $date->format('Y-m-d') === $value;
    }

    private function entitlementMarker(int $year): string
    {
        return self::ENTITLEMENT_PREFIX . $year . ']';
    }

    private function lockExistingEntitlement(int $employeeId, int $leaveTypeId, int $year): void
    {
        $this->db->query(
            'SELECT id FROM entitleddays WHERE employee = ? AND type = ? AND description = ? FOR UPDATE',
            [$employeeId, $leaveTypeId, $this->entitlementMarker($year)]
        )->row_array();
    }

    private function deleteManagedEntitlement(int $employeeId, int $year): void
    {
        $this->db
            ->where('employee', $employeeId)
            ->where('description', $this->entitlementMarker($year))
            ->delete('entitleddays');
    }

    private function deleteManagedEntitlements(): void
    {
        $this->db->like('description', self::ENTITLEMENT_PREFIX, 'after');
        $this->db->delete('entitleddays');
    }

    private function hasManagedEntitlements(): bool
    {
        $this->db->like('description', self::ENTITLEMENT_PREFIX, 'after');
        return $this->db->count_all_results('entitleddays') > 0;
    }

    private function hasRecordedRequests(int $leaveTypeId): bool
    {
        return $this->db
            ->where('type', $leaveTypeId)
            ->count_all_results('leaves') > 0;
    }

    private function formatDays(float $days): string
    {
        if (floor($days) == $days) {
            return (string) (int) $days;
        }
        return rtrim(rtrim(number_format($days, 3, '.', ''), '0'), '.');
    }
}
