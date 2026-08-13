<?php
/**
 * HR-entered usage corrections for leave already taken outside HAYNE Leave.
 *
 * Corrections are materialized as hidden accepted leave rows so every existing
 * HAYNE/Jorani balance and statutory quota continues to use one accounting
 * path. The hidden rows are identified by a reserved cause prefix and are
 * excluded from request lists, calendars, overlap detection and presence views.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_usage_correction_model extends CI_Model
{
    public const CAUSE_PREFIX = '[HAYNE_USAGE_CORRECTION|';

    private const TABLE = 'hayne_usage_corrections';
    private const HISTORY_TABLE = 'hayne_usage_correction_history';

    private const SIMPLE_CODES = [
        'vacation_regular',
        'on_demand',
        'caregiver',
        'force_majeure',
        'childcare',
    ];

    public function __construct()
    {
        parent::__construct();
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if (!$this->db->table_exists(self::TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_usage_corrections` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `employee_id` int(11) NOT NULL,
                    `year` smallint(4) NOT NULL,
                    `code` varchar(32) NOT NULL,
                    `reference_key` varchar(128) NOT NULL DEFAULT '',
                    `leave_id` int(11) NOT NULL,
                    `days` smallint(5) unsigned NOT NULL DEFAULT 0,
                    `created_by` int(11) DEFAULT NULL,
                    `updated_by` int(11) DEFAULT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `employee_year_code_ref` (`employee_id`, `year`, `code`, `reference_key`),
                    UNIQUE KEY `leave_id` (`leave_id`),
                    KEY `employee_year` (`employee_id`, `year`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->db->table_exists(self::HISTORY_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_usage_correction_history` (
                    `id` bigint(20) NOT NULL AUTO_INCREMENT,
                    `employee_id` int(11) NOT NULL,
                    `year` smallint(4) NOT NULL,
                    `code` varchar(32) NOT NULL,
                    `reference_key` varchar(128) NOT NULL DEFAULT '',
                    `old_days` smallint(5) unsigned NOT NULL DEFAULT 0,
                    `new_days` smallint(5) unsigned NOT NULL DEFAULT 0,
                    `changed_by` int(11) DEFAULT NULL,
                    `changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `employee_year` (`employee_id`, `year`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }
    }

    /** @return array<string, mixed>|null */
    public function getCorrection(int $employeeId, int $year, string $code, string $referenceKey = ''): ?array
    {
        $row = $this->db
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('code', $code)
            ->where('reference_key', $referenceKey)
            ->get(self::TABLE)
            ->row_array();
        return empty($row) ? NULL : $row;
    }

    public function getDays(int $employeeId, int $year, string $code, string $referenceKey = ''): int
    {
        $row = $this->getCorrection($employeeId, $year, $code, $referenceKey);
        return empty($row) ? 0 : (int) $row['days'];
    }

    /** @return array<string, int> */
    public function getSimpleMap(int $employeeId, int $year): array
    {
        $result = array_fill_keys(self::SIMPLE_CODES, 0);
        $rows = $this->db
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where_in('code', self::SIMPLE_CODES)
            ->where('reference_key', '')
            ->get(self::TABLE)
            ->result_array();
        foreach ($rows as $row) {
            $result[(string) $row['code']] = (int) $row['days'];
        }
        return $result;
    }

    /** @return array<int, array<string, mixed>> */
    public function getOccasionCorrections(int $employeeId, int $year): array
    {
        return $this->db
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('code', 'occasion')
            ->order_by('reference_key', 'asc')
            ->get(self::TABLE)
            ->result_array();
    }

    /** @return array<int, int> grant id => days */
    public function getHolidayGrantUsage(int $employeeId, int $year): array
    {
        $rows = $this->db
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->where('code', 'holiday_compensation')
            ->get(self::TABLE)
            ->result_array();
        $map = [];
        foreach ($rows as $row) {
            $grantId = (int) $row['reference_key'];
            if ($grantId > 0) {
                $map[$grantId] = (int) $row['days'];
            }
        }
        return $map;
    }

    /** @return array<int, array<string, mixed>> */
    public function getHistory(int $employeeId, int $year, int $limit = 20): array
    {
        return $this->db
            ->where('employee_id', $employeeId)
            ->where('year', $year)
            ->order_by('id', 'desc')
            ->limit(max(1, min(100, $limit)))
            ->get(self::HISTORY_TABLE)
            ->result_array();
    }

    public function setVacation(
        int $employeeId,
        int $year,
        int $totalDays,
        int $onDemandDays,
        int $vacationTypeId,
        int $actorId
    ): void {
        if ($totalDays < 0 || $onDemandDays < 0 || $onDemandDays > 4 || $onDemandDays > $totalDays) {
            throw new InvalidArgumentException('Nieprawidłowa korekta urlopu wypoczynkowego lub urlopu na żądanie.');
        }
        $regularDays = $totalDays - $onDemandDays;
        $this->setSimple($employeeId, $year, 'vacation_regular', $regularDays, $vacationTypeId, $actorId);
        $leaveId = $this->setSimple($employeeId, $year, 'on_demand', $onDemandDays, $vacationTypeId, $actorId);
        if ($leaveId > 0) {
            $this->upsertMeta('hayne_leave_request_meta', $leaveId, ['on_demand' => 1]);
        }
    }

    public function setCaregiver(int $employeeId, int $year, int $days, int $leaveTypeId, int $actorId): void
    {
        $leaveId = $this->setSimple($employeeId, $year, 'caregiver', $days, $leaveTypeId, $actorId);
        if ($leaveId > 0) {
            $this->upsertMeta('hayne_caregiver_request_meta', $leaveId, [
                'person_name' => 'Korekta HR',
                'relation_code' => 'spouse',
                'household_address' => NULL,
                'care_reason' => 'Wykorzystanie zarejestrowane ręcznie przez HR.',
            ]);
        }
    }

    public function setForceMajeure(int $employeeId, int $year, int $days, int $leaveTypeId, int $actorId): void
    {
        $leaveId = $this->setSimple($employeeId, $year, 'force_majeure', $days, $leaveTypeId, $actorId);
        if ($leaveId > 0) {
            $this->upsertMeta('hayne_force_majeure_request_meta', $leaveId, [
                'event_code' => 'illness',
                'immediate_presence' => 1,
            ]);
        }
    }

    public function setChildcare(int $employeeId, int $year, int $days, int $leaveTypeId, int $actorId): void
    {
        $this->setSimple($employeeId, $year, 'childcare', $days, $leaveTypeId, $actorId);
    }

    public function setOccasion(
        int $employeeId,
        int $year,
        string $eventCode,
        string $eventDate,
        int $days,
        int $maxDays,
        int $leaveTypeId,
        int $actorId
    ): void {
        if (!$this->isIsoDate($eventDate) || (int) substr($eventDate, 0, 4) !== $year) {
            throw new InvalidArgumentException('Data zdarzenia okolicznościowego musi należeć do wybranego roku.');
        }
        if ($days < 0 || $days > $maxDays) {
            throw new InvalidArgumentException('Nieprawidłowa liczba dni wykorzystanych dla zdarzenia okolicznościowego.');
        }
        $referenceKey = $eventCode . '|' . $eventDate;
        $leaveId = $this->setMaterialized(
            $employeeId,
            $year,
            'occasion',
            $referenceKey,
            $days,
            $leaveTypeId,
            $actorId
        );
        if ($leaveId <= 0) {
            return;
        }
        $this->db->query(
            'INSERT INTO hayne_occasion_events (employee_id, event_code, event_date, max_days) VALUES (?, ?, ?, ?) '
            . 'ON DUPLICATE KEY UPDATE max_days = VALUES(max_days)',
            [$employeeId, $eventCode, $eventDate, $maxDays]
        );
        $this->upsertMeta('hayne_occasion_request_meta', $leaveId, [
            'event_code' => $eventCode,
            'event_date' => $eventDate,
        ]);
    }

    public function removeOccasion(int $employeeId, int $year, string $referenceKey, int $actorId): void
    {
        $existing = $this->getCorrection($employeeId, $year, 'occasion', $referenceKey);
        if (empty($existing)) {
            return;
        }
        $this->setMaterialized($employeeId, $year, 'occasion', $referenceKey, 0, (int) $this->leaveTypeForCorrection($existing), $actorId);
    }

    public function setHolidayGrant(
        int $employeeId,
        int $year,
        int $grantId,
        bool $used,
        int $leaveTypeId,
        int $actorId
    ): void {
        if ($grantId <= 0) {
            throw new InvalidArgumentException('Nieprawidłowy grant dnia wolnego za święto.');
        }
        $leaveId = $this->setMaterialized(
            $employeeId,
            $year,
            'holiday_compensation',
            (string) $grantId,
            $used ? 1 : 0,
            $leaveTypeId,
            $actorId
        );
        if ($leaveId > 0) {
            $this->upsertMeta('hayne_holiday_compensation_request_meta', $leaveId, ['grant_id' => $grantId]);
        }
    }

    private function setSimple(
        int $employeeId,
        int $year,
        string $code,
        int $days,
        int $leaveTypeId,
        int $actorId
    ): int {
        if (!in_array($code, self::SIMPLE_CODES, TRUE)) {
            throw new InvalidArgumentException('Nieobsługiwany rodzaj korekty wykorzystania.');
        }
        return $this->setMaterialized($employeeId, $year, $code, '', $days, $leaveTypeId, $actorId);
    }

    private function setMaterialized(
        int $employeeId,
        int $year,
        string $code,
        string $referenceKey,
        int $days,
        int $leaveTypeId,
        int $actorId
    ): int {
        if ($employeeId <= 0 || $year < 1970 || $year > 2100 || $leaveTypeId <= 0 || $days < 0 || $days > 366) {
            throw new InvalidArgumentException('Nieprawidłowe dane korekty wykorzystania.');
        }

        $existing = $this->getCorrection($employeeId, $year, $code, $referenceKey);
        $oldDays = empty($existing) ? 0 : (int) $existing['days'];
        if ($oldDays === $days) {
            return empty($existing) ? 0 : (int) $existing['leave_id'];
        }

        if ($days === 0) {
            if (!empty($existing)) {
                $leaveId = (int) $existing['leave_id'];
                $this->deletePolicyMetadata($code, $leaveId);
                $this->db->where('id', $leaveId)->delete('leaves');
                $this->db->where('id', (int) $existing['id'])->delete(self::TABLE);
                $this->writeHistory($employeeId, $year, $code, $referenceKey, $oldDays, 0, $actorId);
            }
            return 0;
        }

        $cause = self::CAUSE_PREFIX . $code . ($referenceKey === '' ? '' : '|' . $referenceKey) . '|' . $year . ']';
        $date = sprintf('%04d-01-01', $year);
        $leaveData = [
            'startdate' => $date,
            'startdatetype' => 'Morning',
            'enddate' => $date,
            'enddatetype' => 'Afternoon',
            'duration' => $days,
            'type' => $leaveTypeId,
            'cause' => $cause,
            'status' => LMS_ACCEPTED,
            'employee' => $employeeId,
            'document' => NULL,
        ];

        if (empty($existing)) {
            $this->db->insert('leaves', $leaveData);
            $leaveId = (int) $this->db->insert_id();
            if ($leaveId <= 0) {
                throw new RuntimeException('Nie udało się utworzyć technicznego wpisu korekty wykorzystania.');
            }
            $this->db->insert(self::TABLE, [
                'employee_id' => $employeeId,
                'year' => $year,
                'code' => $code,
                'reference_key' => $referenceKey,
                'leave_id' => $leaveId,
                'days' => $days,
                'created_by' => $actorId > 0 ? $actorId : NULL,
                'updated_by' => $actorId > 0 ? $actorId : NULL,
            ]);
        } else {
            $leaveId = (int) $existing['leave_id'];
            $this->db->where('id', $leaveId)->update('leaves', $leaveData);
            $this->db->where('id', (int) $existing['id'])->update(self::TABLE, [
                'days' => $days,
                'updated_by' => $actorId > 0 ? $actorId : NULL,
            ]);
        }

        $this->writeHistory($employeeId, $year, $code, $referenceKey, $oldDays, $days, $actorId);
        return $leaveId;
    }

    private function upsertMeta(string $table, int $leaveId, array $data): void
    {
        $data = array_merge(['leave_id' => $leaveId], $data);
        $exists = $this->db->where('leave_id', $leaveId)->count_all_results($table) > 0;
        if ($exists) {
            unset($data['leave_id']);
            $this->db->where('leave_id', $leaveId)->update($table, $data);
        } else {
            $this->db->insert($table, $data);
        }
    }

    private function deletePolicyMetadata(string $code, int $leaveId): void
    {
        $table = NULL;
        switch ($code) {
            case 'on_demand':
                $table = 'hayne_leave_request_meta';
                break;
            case 'caregiver':
                $table = 'hayne_caregiver_request_meta';
                break;
            case 'force_majeure':
                $table = 'hayne_force_majeure_request_meta';
                break;
            case 'occasion':
                $table = 'hayne_occasion_request_meta';
                break;
            case 'holiday_compensation':
                $table = 'hayne_holiday_compensation_request_meta';
                break;
        }
        if ($table !== NULL && $this->db->table_exists($table)) {
            $this->db->where('leave_id', $leaveId)->delete($table);
        }
    }

    private function writeHistory(
        int $employeeId,
        int $year,
        string $code,
        string $referenceKey,
        int $oldDays,
        int $newDays,
        int $actorId
    ): void {
        $this->db->insert(self::HISTORY_TABLE, [
            'employee_id' => $employeeId,
            'year' => $year,
            'code' => $code,
            'reference_key' => $referenceKey,
            'old_days' => $oldDays,
            'new_days' => $newDays,
            'changed_by' => $actorId > 0 ? $actorId : NULL,
        ]);
    }

    private function leaveTypeForCorrection(array $correction): int
    {
        $row = $this->db->select('type')->where('id', (int) $correction['leave_id'])->get('leaves')->row_array();
        return empty($row) ? 0 : (int) $row['type'];
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== FALSE && $date->format('Y-m-d') === $value;
    }
}
