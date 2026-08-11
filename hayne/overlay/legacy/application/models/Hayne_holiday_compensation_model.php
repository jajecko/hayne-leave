<?php
/**
 * HAYNE replacement day off for a public holiday reducing working-time
 * dimension in the employee's settlement period.
 *
 * This is not annual vacation credit. HR grants one concrete day-off right
 * for one source holiday and defines the settlement-period boundaries.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_holiday_compensation_model extends CI_Model
{
    public const POLICY_CODE = 'holiday_compensation';
    public const MANAGED_TYPE_NAME = 'Dzień wolny za święto';
    public const MANAGED_TYPE_ACRONYM = 'DWS';

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';
    private const GRANT_TABLE = 'hayne_holiday_compensation_grants';
    private const META_TABLE = 'hayne_holiday_compensation_request_meta';

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

        if (!$this->db->table_exists(self::GRANT_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_holiday_compensation_grants` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `employee_id` int(11) NOT NULL,
                    `source_holiday_date` date NOT NULL,
                    `period_start` date NOT NULL,
                    `period_end` date NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `employee_holiday` (`employee_id`, `source_holiday_date`),
                    KEY `period_end` (`period_end`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->db->table_exists(self::META_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_holiday_compensation_request_meta` (
                    `leave_id` int(11) NOT NULL,
                    `grant_id` int(11) NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`leave_id`),
                    KEY `grant_id` (`grant_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    /**
     * Ensure a dedicated ordinary Jorani leave type exists without assuming an ID.
     */
    public function ensureManagedLeaveType(): int
    {
        $row = $this->db
            ->where('name', self::MANAGED_TYPE_NAME)
            ->get('types')
            ->row_array();
        if (!empty($row)) {
            return (int) $row['id'];
        }

        $this->db->insert('types', [
            'name' => self::MANAGED_TYPE_NAME,
            'acronym' => self::MANAGED_TYPE_ACRONYM,
            'deduct_days_off' => 1,
        ]);
        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            $row = $this->db
                ->where('name', self::MANAGED_TYPE_NAME)
                ->get('types')
                ->row_array();
            $id = empty($row) ? 0 : (int) $row['id'];
        }
        if ($id <= 0) {
            throw new RuntimeException('Nie udało się utworzyć rodzaju „Dzień wolny za święto”.');
        }
        return $id;
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
            throw new InvalidArgumentException('Nieprawidłowy rodzaj dnia wolnego za święto.');
        }

        $existing = $this->getPolicy();
        if (
            !empty($existing) &&
            (int) $existing['leave_type_id'] !== $leaveTypeId &&
            $this->hasRecordedRequests()
        ) {
            throw new InvalidArgumentException(
                'Nie można zmienić rodzaju dnia wolnego za święto po zapisaniu wniosków.'
            );
        }
        if (!$enabled && $this->hasRecordedRequests()) {
            throw new InvalidArgumentException(
                'Nie można wyłączyć obsługi dnia wolnego za święto po zapisaniu wniosków.'
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
            $this->db->where('policy_code', self::POLICY_CODE)->update(self::POLICY_TABLE, $data);
        }
    }

    public function isHolidayCompensationType(int $leaveTypeId): bool
    {
        $policy = $this->getPolicy();
        return !empty($policy)
            && (int) $policy['enabled'] === 1
            && (int) $policy['leave_type_id'] === $leaveTypeId;
    }

    public function isHolidayCompensationLeave(int $leaveId): bool
    {
        return $this->db->where('leave_id', $leaveId)->count_all_results(self::META_TABLE) > 0;
    }

    public function saveGrant(
        int $employeeId,
        string $sourceHolidayDate,
        string $periodStart,
        string $periodEnd
    ): int {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Nieprawidłowy pracownik.');
        }
        foreach ([$sourceHolidayDate, $periodStart, $periodEnd] as $date) {
            if (!$this->isIsoDate($date)) {
                throw new InvalidArgumentException('Podaj prawidłowe daty dla dnia wolnego za święto.');
            }
        }
        if ($periodStart > $periodEnd) {
            throw new InvalidArgumentException('Początek okresu rozliczeniowego nie może być po jego końcu.');
        }
        if ($sourceHolidayDate < $periodStart || $sourceHolidayDate > $periodEnd) {
            throw new InvalidArgumentException('Święto źródłowe musi przypadać w podanym okresie rozliczeniowym.');
        }

        $existing = $this->db
            ->where('employee_id', $employeeId)
            ->where('source_holiday_date', $sourceHolidayDate)
            ->get(self::GRANT_TABLE)
            ->row_array();

        if (!empty($existing)) {
            $id = (int) $existing['id'];
            if (
                (string) $existing['period_start'] === $periodStart &&
                (string) $existing['period_end'] === $periodEnd
            ) {
                return $id;
            }
            if ($this->grantHasRequests($id)) {
                throw new InvalidArgumentException(
                    'Nie można zmienić okresu rozliczeniowego grantu, który został już użyty we wniosku.'
                );
            }
            $this->db->where('id', $id)->update(self::GRANT_TABLE, [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ]);
            return $id;
        }

        $this->db->insert(self::GRANT_TABLE, [
            'employee_id' => $employeeId,
            'source_holiday_date' => $sourceHolidayDate,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
        ]);
        $id = (int) $this->db->insert_id();
        if ($id <= 0) {
            throw new RuntimeException('Nie udało się przyznać dnia wolnego za święto.');
        }
        return $id;
    }

    public function getGrant(int $grantId): ?array
    {
        $row = $this->db->where('id', $grantId)->get(self::GRANT_TABLE)->row_array();
        return empty($row) ? NULL : $row;
    }

    public function getGrants(): array
    {
        $this->db->select('g.*, u.firstname, u.lastname');
        $this->db->from(self::GRANT_TABLE . ' g');
        $this->db->join('users u', 'u.id = g.employee_id', 'left');
        $this->db->order_by('g.source_holiday_date', 'DESC');
        $this->db->order_by('g.id', 'DESC');
        $rows = $this->db->get()->result_array();
        foreach ($rows as &$row) {
            $row['reserved'] = $this->getReservedUsage((int) $row['id'], NULL);
        }
        unset($row);
        return $rows;
    }

    public function getMetadata(int $leaveId): ?array
    {
        $row = $this->db->where('leave_id', $leaveId)->get(self::META_TABLE)->row_array();
        return empty($row) ? NULL : $row;
    }

    public function getMetadataForDisplay(int $leaveId): ?array
    {
        $metadata = $this->getMetadata($leaveId);
        if (empty($metadata)) {
            return NULL;
        }
        $grant = $this->getGrant((int) $metadata['grant_id']);
        if (empty($grant)) {
            return $metadata;
        }
        return array_merge($metadata, [
            'source_holiday_date' => $grant['source_holiday_date'],
            'period_start' => $grant['period_start'],
            'period_end' => $grant['period_end'],
        ]);
    }

    public function getFormState(int $employeeId, ?int $leaveId = NULL): array
    {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            return ['enabled' => FALSE];
        }

        $current = $leaveId === NULL ? NULL : $this->getMetadata($leaveId);
        $currentGrantId = empty($current) ? 0 : (int) $current['grant_id'];
        $grants = $this->db
            ->where('employee_id', $employeeId)
            ->order_by('source_holiday_date', 'DESC')
            ->get(self::GRANT_TABLE)
            ->result_array();
        $available = [];
        foreach ($grants as $grant) {
            $grantId = (int) $grant['id'];
            $used = $this->getReservedUsage($grantId, $leaveId);
            if ($used < 1 || $grantId === $currentGrantId) {
                $grant['reserved'] = $used;
                $available[] = $grant;
            }
        }

        return [
            'enabled' => TRUE,
            'leave_type_id' => (int) $policy['leave_type_id'],
            'grants' => $available,
            'current' => $current,
        ];
    }

    public function normalizeGrantId($value): int
    {
        $grantId = filter_var($value, FILTER_VALIDATE_INT);
        if ($grantId === FALSE || $grantId <= 0) {
            throw new InvalidArgumentException('Wybierz przyznany dzień wolny za święto.');
        }
        return (int) $grantId;
    }

    /** Caller keeps the transaction open until leave/meta write is committed. */
    public function lockGrant(int $employeeId, int $grantId): array
    {
        $row = $this->db->query(
            'SELECT * FROM hayne_holiday_compensation_grants WHERE id = ? AND employee_id = ? FOR UPDATE',
            [$grantId, $employeeId]
        )->row_array();
        if (empty($row)) {
            throw new InvalidArgumentException('Nie znaleziono przyznanego dnia wolnego za święto dla tego pracownika.');
        }
        return $row;
    }

    public function assertRequestAllowed(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $duration,
        int $status,
        int $grantId,
        ?int $excludeLeaveId = NULL
    ): int {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Dzień wolny za święto nie jest skonfigurowany.');
        }
        if ((int) $policy['leave_type_id'] !== $leaveTypeId) {
            throw new InvalidArgumentException('Wniosek nie używa skonfigurowanego rodzaju dnia wolnego za święto.');
        }
        if (abs($duration - 1.0) > 0.0001) {
            throw new InvalidArgumentException('Dzień wolny za święto musi obejmować dokładnie 1 pełny dzień.');
        }
        if (!$this->isIsoDate($startDate) || !$this->isIsoDate($endDate) || $startDate !== $endDate) {
            throw new InvalidArgumentException('Dzień wolny za święto musi obejmować jeden dzień kalendarzowy.');
        }

        $grant = $this->getGrant($grantId);
        if (empty($grant) || (int) $grant['employee_id'] !== $employeeId) {
            throw new InvalidArgumentException('Wybrany grant nie należy do tego pracownika.');
        }
        if ($startDate < (string) $grant['period_start'] || $startDate > (string) $grant['period_end']) {
            throw new InvalidArgumentException(
                'Dzień wolny musi przypadać w okresie rozliczeniowym ' .
                $grant['period_start'] . ' – ' . $grant['period_end'] . '.'
            );
        }

        if ($this->statusReservesGrant($status)) {
            $used = $this->getReservedUsage($grantId, $excludeLeaveId);
            if ($used >= 1) {
                throw new InvalidArgumentException('Ten dzień wolny za święto został już wykorzystany lub zarezerwowany.');
            }
        }
        return $grantId;
    }

    public function setMetadata(int $leaveId, int $grantId): void
    {
        $data = ['leave_id' => $leaveId, 'grant_id' => $grantId];
        if ($this->getMetadata($leaveId) === NULL) {
            $this->db->insert(self::META_TABLE, $data);
        } else {
            $this->db->where('leave_id', $leaveId)->update(self::META_TABLE, $data);
        }
    }

    public function deleteMetadata(int $leaveId): void
    {
        $this->db->where('leave_id', $leaveId)->delete(self::META_TABLE);
    }

    private function getReservedUsage(int $grantId, ?int $excludeLeaveId): int
    {
        $this->db->from('leaves l');
        $this->db->join(self::META_TABLE . ' m', 'm.leave_id = l.id', 'inner');
        $this->db->where('m.grant_id', $grantId);
        $this->db->where_in('l.status', [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION]);
        if ($excludeLeaveId !== NULL) {
            $this->db->where('l.id !=', $excludeLeaveId);
        }
        return (int) $this->db->count_all_results();
    }

    private function statusReservesGrant(int $status): bool
    {
        return in_array($status, [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION], TRUE);
    }

    private function grantHasRequests(int $grantId): bool
    {
        return $this->db->where('grant_id', $grantId)->count_all_results(self::META_TABLE) > 0;
    }

    private function hasRecordedRequests(): bool
    {
        return $this->db->count_all_results(self::META_TABLE) > 0;
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== FALSE && $date->format('Y-m-d') === $value;
    }
}
