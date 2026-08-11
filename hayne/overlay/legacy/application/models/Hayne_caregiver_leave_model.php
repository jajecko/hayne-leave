<?php
/**
 * HAYNE caregiver leave policy (Polish Labour Code art. 1731).
 *
 * Jorani entitleddays stays the source of truth for granted credit. HAYNE adds
 * the statutory policy mapping, required request metadata and annual-limit
 * validation for the whole-day-only product scope.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_caregiver_leave_model extends CI_Model
{
    public const POLICY_CODE = 'caregiver';
    public const ANNUAL_LIMIT_DAYS = 5;

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';
    private const META_TABLE = 'hayne_caregiver_request_meta';
    private const ENTITLEMENT_PREFIX = '[HAYNE_STATUTORY|caregiver|';

    private const RELATIONS = [
        'son',
        'daughter',
        'mother',
        'father',
        'spouse',
        'household',
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
                "CREATE TABLE IF NOT EXISTS `hayne_caregiver_request_meta` (
                    `leave_id` int(11) NOT NULL,
                    `person_name` varchar(190) NOT NULL,
                    `relation_code` varchar(32) NOT NULL,
                    `household_address` varchar(255) DEFAULT NULL,
                    `care_reason` text NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`leave_id`),
                    KEY `relation_code` (`relation_code`)
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
            throw new InvalidArgumentException('Nieprawidłowy rodzaj urlopu opiekuńczego.');
        }

        $existing = $this->getPolicy();
        if (!empty($existing) && (int) $existing['leave_type_id'] !== $leaveTypeId) {
            if ($this->hasRecordedCaregiverRequests()) {
                throw new InvalidArgumentException(
                    'Nie można zmienić rodzaju urlopu opiekuńczego po zapisaniu wniosków.'
                );
            }
            $this->deleteManagedEntitlements();
        }

        if (!$enabled && $this->hasRecordedCaregiverRequests()) {
            throw new InvalidArgumentException(
                'Nie można wyłączyć urlopu opiekuńczego po zapisaniu wniosków.'
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

    public function isCaregiverType(int $leaveTypeId): bool
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
     * Idempotently grants five days for one calendar year. There is no
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
     * inside a DB transaction and must keep it open until the leave/meta write.
     */
    public function lockEmployeeYear(int $employeeId, int $year): void
    {
        $this->ensureYear($employeeId, $year);
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Urlop opiekuńczy nie jest skonfigurowany.');
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
            throw new RuntimeException('Nie udało się zablokować rocznej puli urlopu opiekuńczego.');
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

    public function isCaregiverLeave(int $leaveId): bool
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

    /** @return array<string, string|null> */
    public function normalizeDetails(array $details): array
    {
        $personName = trim((string) ($details['person_name'] ?? ''));
        $relationCode = trim((string) ($details['relation_code'] ?? ''));
        $householdAddress = trim((string) ($details['household_address'] ?? ''));
        $careReason = trim((string) ($details['care_reason'] ?? ''));

        if ($personName === '') {
            throw new InvalidArgumentException('Podaj imię i nazwisko osoby wymagającej opieki lub wsparcia.');
        }
        if (mb_strlen($personName) > 190) {
            throw new InvalidArgumentException('Imię i nazwisko osoby jest zbyt długie.');
        }
        if (!in_array($relationCode, self::RELATIONS, TRUE)) {
            throw new InvalidArgumentException('Wybierz relację z osobą wymagającą opieki lub wsparcia.');
        }
        if ($careReason === '') {
            throw new InvalidArgumentException('Podaj przyczynę konieczności zapewnienia opieki lub wsparcia.');
        }
        if (mb_strlen($careReason) > 4000) {
            throw new InvalidArgumentException('Opis przyczyny jest zbyt długi.');
        }
        if ($relationCode === 'household' && $householdAddress === '') {
            throw new InvalidArgumentException('Podaj adres zamieszkania osoby z tego samego gospodarstwa domowego.');
        }
        if (mb_strlen($householdAddress) > 255) {
            throw new InvalidArgumentException('Adres zamieszkania jest zbyt długi.');
        }

        return [
            'person_name' => $personName,
            'relation_code' => $relationCode,
            'household_address' => $relationCode === 'household' ? $householdAddress : NULL,
            'care_reason' => $careReason,
        ];
    }

    /**
     * Authoritative create/edit/plan validation. Planned records do not reserve
     * the annual limit; submission rechecks the limit and one-day deadline.
     *
     * @return array<string, string|null>
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
        bool $enforceAdvance = TRUE
    ): array {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Urlop opiekuńczy nie jest skonfigurowany.');
        }
        if ((int) $policy['leave_type_id'] !== $leaveTypeId) {
            throw new InvalidArgumentException('Wniosek nie używa skonfigurowanego rodzaju urlopu opiekuńczego.');
        }
        if ($duration <= 0 || floor($duration) != $duration) {
            throw new InvalidArgumentException('Urlop opiekuńczy jest obsługiwany wyłącznie w pełnych dniach.');
        }
        if ($duration > self::ANNUAL_LIMIT_DAYS) {
            throw new InvalidArgumentException('Pojedynczy wniosek o urlop opiekuńczy nie może przekraczać 5 dni.');
        }
        if (!$this->isIsoDate($startDate) || !$this->isIsoDate($endDate) || $startDate > $endDate) {
            throw new InvalidArgumentException('Nieprawidłowy zakres dat urlopu opiekuńczego.');
        }

        $startYear = (int) substr($startDate, 0, 4);
        $endYear = (int) substr($endDate, 0, 4);
        if ($startYear !== $endYear) {
            throw new InvalidArgumentException('Jeden wniosek o urlop opiekuńczy nie może obejmować dwóch lat kalendarzowych.');
        }

        $normalized = $this->normalizeDetails($details);
        $this->ensureYear($employeeId, $startYear);

        if ($this->statusReservesLimit($status)) {
            if (
                $enforceAdvance &&
                ($status === LMS_REQUESTED || $status === LMS_ACCEPTED) &&
                $startDate <= date('Y-m-d')
            ) {
                throw new InvalidArgumentException('Wniosek o urlop opiekuńczy złóż co najmniej 1 dzień przed jego rozpoczęciem.');
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
                    'Przekroczony limit urlopu opiekuńczego. Pozostało: ' . $this->formatDays($remaining) . ' dni.'
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
            'person_name' => $normalized['person_name'],
            'relation_code' => $normalized['relation_code'],
            'household_address' => $normalized['household_address'],
            'care_reason' => $normalized['care_reason'],
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

    private function hasRecordedCaregiverRequests(): bool
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
