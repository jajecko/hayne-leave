<?php
/**
 * HAYNE force-majeure leave policy (Polish Labour Code art. 1481).
 *
 * Product scope is whole-day only. Jorani entitleddays remains the source of
 * truth for the granted two-day annual credit. HAYNE adds statutory mapping,
 * minimal request metadata and authoritative annual-limit validation.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_force_majeure_model extends CI_Model
{
    public const POLICY_CODE = 'force_majeure';
    public const ANNUAL_LIMIT_DAYS = 2;

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';
    private const META_TABLE = 'hayne_force_majeure_request_meta';
    private const ENTITLEMENT_PREFIX = '[HAYNE_STATUTORY|force_majeure|';

    private const EVENTS = [
        'illness',
        'accident',
    ];

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

        if (!$this->db->table_exists(self::META_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_force_majeure_request_meta` (
                    `leave_id` int(11) NOT NULL,
                    `event_code` varchar(16) NOT NULL,
                    `immediate_presence` tinyint(1) NOT NULL DEFAULT 1,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`leave_id`),
                    KEY `event_code` (`event_code`)
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
            throw new InvalidArgumentException('Nieprawidłowy rodzaj zwolnienia z powodu siły wyższej.');
        }

        $existing = $this->getPolicy();
        if (!empty($existing) && (int) $existing['leave_type_id'] !== $leaveTypeId) {
            if ($this->hasRecordedRequests()) {
                throw new InvalidArgumentException(
                    'Nie można zmienić rodzaju zwolnienia z powodu siły wyższej po zapisaniu wniosków.'
                );
            }
            $this->deleteManagedEntitlements();
        }

        if (!$enabled && $this->hasRecordedRequests()) {
            throw new InvalidArgumentException(
                'Nie można wyłączyć zwolnienia z powodu siły wyższej po zapisaniu wniosków.'
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

    public function isForceMajeureType(int $leaveTypeId): bool
    {
        $policy = $this->getPolicy();
        return !empty($policy)
            && (int) $policy['enabled'] === 1
            && (int) $policy['leave_type_id'] === $leaveTypeId;
    }

    public function ensureCurrentYear(int $employeeId): void
    {
        $this->ensureYear($employeeId, (int) date('Y'));
    }

    /**
     * Idempotently grants exactly two days for one calendar year. There is no
     * carry-over path for this statutory pool.
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

        $leaveTypeId = (int) $policy['leave_type_id'];
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);
        $description = $this->entitlementMarker($year);

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
            'days' => self::ANNUAL_LIMIT_DAYS,
            'type' => $leaveTypeId,
            'description' => $description,
        ];

        if (empty($existing)) {
            $this->db->insert('entitleddays', $data);
        } elseif ((float) $existing['days'] !== (float) self::ANNUAL_LIMIT_DAYS) {
            $this->db
                ->where('id', (int) $existing['id'])
                ->update('entitleddays', ['days' => self::ANNUAL_LIMIT_DAYS]);
        }
    }

    /**
     * Serialize annual-limit checks per employee/year. Caller must already be
     * inside a DB transaction and keep it open until the leave/meta write.
     */
    public function lockEmployeeYear(int $employeeId, int $year): void
    {
        $this->ensureYear($employeeId, $year);
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Zwolnienie z powodu siły wyższej nie jest skonfigurowane.');
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
            throw new RuntimeException('Nie udało się zablokować rocznej puli zwolnienia z powodu siły wyższej.');
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

        $this->ensureYear($employeeId, $year);
        $leaveTypeId = (int) $policy['leave_type_id'];
        $used = $this->getReservedUsage($employeeId, $leaveTypeId, $year, $leaveId);

        return [
            'enabled' => TRUE,
            'year' => $year,
            'leave_type_id' => $leaveTypeId,
            'limit' => self::ANNUAL_LIMIT_DAYS,
            'used' => $used,
            'remaining' => max(0.0, self::ANNUAL_LIMIT_DAYS - $used),
            'current' => $leaveId === NULL ? NULL : $this->getMetadata($leaveId),
        ];
    }

    public function getSummary(int $employeeId, int $year): ?array
    {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            return NULL;
        }

        $this->ensureYear($employeeId, $year);
        $used = $this->getReservedUsage($employeeId, (int) $policy['leave_type_id'], $year, NULL);

        return [
            'year' => $year,
            'limit' => self::ANNUAL_LIMIT_DAYS,
            'used' => $used,
            'remaining' => max(0.0, self::ANNUAL_LIMIT_DAYS - $used),
            'leave_type_id' => (int) $policy['leave_type_id'],
        ];
    }

    public function isForceMajeureLeave(int $leaveId): bool
    {
        return $this->db
            ->where('leave_id', $leaveId)
            ->count_all_results(self::META_TABLE) > 0;
    }

    public function getMetadata(int $leaveId): ?array
    {
        $row = $this->db
            ->where('leave_id', $leaveId)
            ->get(self::META_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    /** @return array<string, int|string> */
    public function normalizeDetails(array $details): array
    {
        $eventCode = trim((string) ($details['event_code'] ?? ''));
        $immediatePresence = (int) ($details['immediate_presence'] ?? 0);

        if (!in_array($eventCode, self::EVENTS, TRUE)) {
            throw new InvalidArgumentException('Wybierz, czy pilna sprawa rodzinna wynika z choroby czy wypadku.');
        }
        if ($immediatePresence !== 1) {
            throw new InvalidArgumentException('Potwierdź, że Twoja natychmiastowa obecność jest niezbędna.');
        }

        return [
            'event_code' => $eventCode,
            'immediate_presence' => 1,
        ];
    }

    /**
     * Authoritative create/edit/plan validation. Planned records do not reserve
     * the annual limit. Requested/accepted requests may be submitted on the day
     * of use, but never after the requested absence has already started.
     *
     * @return array<string, int|string>
     */
    public function assertRequestAllowed(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $duration,
        int $status,
        array $details,
        ?int $excludeLeaveId = NULL,
        bool $enforceDeadline = TRUE
    ): array {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Zwolnienie z powodu siły wyższej nie jest skonfigurowane.');
        }
        if ((int) $policy['leave_type_id'] !== $leaveTypeId) {
            throw new InvalidArgumentException('Wniosek nie używa skonfigurowanego rodzaju zwolnienia z powodu siły wyższej.');
        }
        if ($duration <= 0 || floor($duration) != $duration) {
            throw new InvalidArgumentException('Zwolnienie z powodu siły wyższej jest obsługiwane wyłącznie w pełnych dniach.');
        }
        if ($duration > self::ANNUAL_LIMIT_DAYS) {
            throw new InvalidArgumentException('Pojedynczy wniosek z powodu siły wyższej nie może przekraczać 2 dni.');
        }
        if (!$this->isIsoDate($startDate) || !$this->isIsoDate($endDate) || $startDate > $endDate) {
            throw new InvalidArgumentException('Nieprawidłowy zakres dat zwolnienia z powodu siły wyższej.');
        }

        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        if ($startYear !== $endYear) {
            throw new InvalidArgumentException('Jeden wniosek z powodu siły wyższej nie może obejmować dwóch lat kalendarzowych.');
        }

        $normalized = $this->normalizeDetails($details);
        $this->ensureYear($employeeId, $startYear);

        if ($this->statusReservesLimit($status)) {
            if (
                $enforceDeadline &&
                ($status === LMS_REQUESTED || $status === LMS_ACCEPTED) &&
                $startDate < date('Y-m-d')
            ) {
                throw new InvalidArgumentException(
                    'Wniosek o zwolnienie z powodu siły wyższej złóż najpóźniej w dniu korzystania z tego zwolnienia.'
                );
            }

            $used = $this->getReservedUsage(
                $employeeId,
                $leaveTypeId,
                $startYear,
                $excludeLeaveId
            );
            $remaining = max(0.0, self::ANNUAL_LIMIT_DAYS - $used);
            if ($duration > $remaining) {
                throw new InvalidArgumentException(
                    'Przekroczony limit zwolnienia z powodu siły wyższej. Pozostało: ' .
                    $this->formatDays($remaining) . ' dni.'
                );
            }
        }

        return $normalized;
    }

    public function setMetadata(int $leaveId, array $details): void
    {
        $normalized = $this->normalizeDetails($details);
        $existing = $this->getMetadata($leaveId);
        $data = [
            'leave_id' => $leaveId,
            'event_code' => $normalized['event_code'],
            'immediate_presence' => 1,
        ];

        if (empty($existing)) {
            $this->db->insert(self::META_TABLE, $data);
        } else {
            $this->db->where('leave_id', $leaveId)->update(self::META_TABLE, $data);
        }
    }

    public function deleteMetadata(int $leaveId): void
    {
        $this->db->where('leave_id', $leaveId)->delete(self::META_TABLE);
    }

    private function getReservedUsage(
        int $employeeId,
        int $leaveTypeId,
        int $year,
        ?int $excludeLeaveId
    ): float {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $this->db->select('COALESCE(SUM(l.duration), 0) AS used', FALSE);
        $this->db->from('leaves l');
        $this->db->join(self::META_TABLE . ' m', 'm.leave_id = l.id', 'inner');
        $this->db->where('l.employee', $employeeId);
        $this->db->where('l.type', $leaveTypeId);
        $this->db->where_in('l.status', [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION]);
        $this->db->where('l.startdate >=', $startDate);
        $this->db->where('l.enddate <=', $endDate);
        if ($excludeLeaveId !== NULL) {
            $this->db->where('l.id !=', $excludeLeaveId);
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

    private function hasRecordedRequests(): bool
    {
        return $this->db->count_all_results(self::META_TABLE) > 0;
    }

    private function deleteManagedEntitlements(): void
    {
        $this->db->like('description', self::ENTITLEMENT_PREFIX, 'after');
        $this->db->delete('entitleddays');
    }

    private function formatDays(float $days): string
    {
        if (floor($days) == $days) {
            return (string) (int) $days;
        }
        return rtrim(rtrim(number_format($days, 3, '.', ''), '0'), '.');
    }
}
