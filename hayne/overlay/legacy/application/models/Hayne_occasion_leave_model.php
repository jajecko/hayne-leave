<?php
/**
 * HAYNE occasion leave policy (Polish regulation of 15 May 1996, paragraph 15).
 *
 * Occasion leave is event-based, not an annual leave pool. HAYNE stores only
 * the event category and its canonical date. For death/funeral categories the
 * canonical date is the date of death, so split requests share one event cap.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_occasion_leave_model extends CI_Model
{
    public const POLICY_CODE = 'occasion';

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';
    private const META_TABLE = 'hayne_occasion_request_meta';
    private const EVENT_TABLE = 'hayne_occasion_events';

    private const EVENTS = [
        'employee_marriage' => ['label' => 'Ślub pracownika', 'days' => 2],
        'child_birth' => ['label' => 'Urodzenie dziecka pracownika', 'days' => 2],
        'spouse_death' => ['label' => 'Zgon i pogrzeb małżonka / małżonki', 'days' => 2],
        'child_death' => ['label' => 'Zgon i pogrzeb dziecka', 'days' => 2],
        'father_death' => ['label' => 'Zgon i pogrzeb ojca', 'days' => 2],
        'mother_death' => ['label' => 'Zgon i pogrzeb matki', 'days' => 2],
        'stepfather_death' => ['label' => 'Zgon i pogrzeb ojczyma', 'days' => 2],
        'stepmother_death' => ['label' => 'Zgon i pogrzeb macochy', 'days' => 2],
        'child_marriage' => ['label' => 'Ślub dziecka pracownika', 'days' => 1],
        'sister_death' => ['label' => 'Zgon i pogrzeb siostry', 'days' => 1],
        'brother_death' => ['label' => 'Zgon i pogrzeb brata', 'days' => 1],
        'mother_in_law_death' => ['label' => 'Zgon i pogrzeb teściowej', 'days' => 1],
        'father_in_law_death' => ['label' => 'Zgon i pogrzeb teścia', 'days' => 1],
        'grandmother_death' => ['label' => 'Zgon i pogrzeb babki', 'days' => 1],
        'grandfather_death' => ['label' => 'Zgon i pogrzeb dziadka', 'days' => 1],
        'dependent_person_death' => [
            'label' => 'Zgon i pogrzeb innej osoby pozostającej na utrzymaniu pracownika',
            'days' => 1,
        ],
        'cared_person_death' => [
            'label' => 'Zgon i pogrzeb innej osoby pozostającej pod bezpośrednią opieką pracownika',
            'days' => 1,
        ],
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
                "CREATE TABLE IF NOT EXISTS `hayne_occasion_request_meta` (
                    `leave_id` int(11) NOT NULL,
                    `event_code` varchar(48) NOT NULL,
                    `event_date` date NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`leave_id`),
                    KEY `event_lookup` (`event_code`, `event_date`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        }

        if (!$this->db->table_exists(self::EVENT_TABLE)) {
            $this->db->query(
                "CREATE TABLE IF NOT EXISTS `hayne_occasion_events` (
                    `employee_id` int(11) NOT NULL,
                    `event_code` varchar(48) NOT NULL,
                    `event_date` date NOT NULL,
                    `max_days` tinyint(1) NOT NULL,
                    `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`employee_id`, `event_code`, `event_date`),
                    KEY `event_date` (`event_date`)
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
            throw new InvalidArgumentException('Nieprawidłowy rodzaj urlopu okolicznościowego.');
        }

        $existing = $this->getPolicy();
        if (
            !empty($existing) &&
            (int) $existing['leave_type_id'] !== $leaveTypeId &&
            $this->hasRecordedRequests()
        ) {
            throw new InvalidArgumentException(
                'Nie można zmienić rodzaju urlopu okolicznościowego po zapisaniu wniosków.'
            );
        }

        if (!$enabled && $this->hasRecordedRequests()) {
            throw new InvalidArgumentException(
                'Nie można wyłączyć urlopu okolicznościowego po zapisaniu wniosków.'
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
    }

    public function isEnabled(): bool
    {
        $policy = $this->getPolicy();
        return !empty($policy) && (int) $policy['enabled'] === 1;
    }

    public function isOccasionType(int $leaveTypeId): bool
    {
        $policy = $this->getPolicy();
        return !empty($policy)
            && (int) $policy['enabled'] === 1
            && (int) $policy['leave_type_id'] === $leaveTypeId;
    }

    public function isOccasionLeave(int $leaveId): bool
    {
        return $this->db
            ->where('leave_id', $leaveId)
            ->count_all_results(self::META_TABLE) > 0;
    }

    public function getEventDefinitions(): array
    {
        return self::EVENTS;
    }

    public function getFormState(int $employeeId, ?int $leaveId = NULL): array
    {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            return ['enabled' => FALSE];
        }

        return [
            'enabled' => TRUE,
            'leave_type_id' => (int) $policy['leave_type_id'],
            'events' => self::EVENTS,
            'current' => $leaveId === NULL ? NULL : $this->getMetadata($leaveId),
        ];
    }

    public function getMetadata(int $leaveId): ?array
    {
        $row = $this->db
            ->where('leave_id', $leaveId)
            ->get(self::META_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    public function getMetadataForDisplay(int $leaveId): ?array
    {
        $metadata = $this->getMetadata($leaveId);
        if (empty($metadata)) {
            return NULL;
        }

        $definition = self::EVENTS[(string) $metadata['event_code']] ?? NULL;
        if ($definition === NULL) {
            return $metadata;
        }

        $metadata['event_label'] = $definition['label'];
        $metadata['max_days'] = (int) $definition['days'];
        return $metadata;
    }

    /** @return array{event_code:string,event_date:string} */
    public function normalizeDetails(array $details): array
    {
        $eventCode = trim((string) ($details['event_code'] ?? ''));
        $eventDate = trim((string) ($details['event_date'] ?? ''));

        if (!array_key_exists($eventCode, self::EVENTS)) {
            throw new InvalidArgumentException('Wybierz zdarzenie uprawniające do urlopu okolicznościowego.');
        }
        if (!$this->isIsoDate($eventDate)) {
            throw new InvalidArgumentException('Podaj prawidłową datę zdarzenia.');
        }

        return [
            'event_code' => $eventCode,
            'event_date' => $eventDate,
        ];
    }

    /**
     * Serialize quota checks for one employee and one concrete event.
     * Caller must keep the transaction open through the leave/meta write.
     */
    public function lockEvent(int $employeeId, string $eventCode, string $eventDate): void
    {
        if ($employeeId <= 0) {
            throw new InvalidArgumentException('Nieprawidłowy pracownik dla urlopu okolicznościowego.');
        }

        $normalized = $this->normalizeDetails([
            'event_code' => $eventCode,
            'event_date' => $eventDate,
        ]);
        $definition = self::EVENTS[$normalized['event_code']];
        $maxDays = (int) $definition['days'];

        $this->db->query(
            'INSERT IGNORE INTO hayne_occasion_events (employee_id, event_code, event_date, max_days) VALUES (?, ?, ?, ?)',
            [$employeeId, $normalized['event_code'], $normalized['event_date'], $maxDays]
        );

        $row = $this->db->query(
            'SELECT max_days FROM hayne_occasion_events WHERE employee_id = ? AND event_code = ? AND event_date = ? FOR UPDATE',
            [$employeeId, $normalized['event_code'], $normalized['event_date']]
        )->row_array();

        if (empty($row)) {
            throw new RuntimeException('Nie udało się zablokować zdarzenia urlopu okolicznościowego.');
        }

        if ((int) $row['max_days'] !== $maxDays) {
            $this->db
                ->where('employee_id', $employeeId)
                ->where('event_code', $normalized['event_code'])
                ->where('event_date', $normalized['event_date'])
                ->update(self::EVENT_TABLE, ['max_days' => $maxDays]);
        }
    }

    /**
     * Authoritative create/edit/plan validation. There is no annual balance.
     * Requested, accepted and cancellation-pending requests reserve only the
     * quota of their concrete event. Planned requests keep metadata but reserve
     * nothing until submission.
     *
     * @return array{event_code:string,event_date:string}
     */
    public function assertRequestAllowed(
        int $employeeId,
        int $leaveTypeId,
        string $startDate,
        string $endDate,
        float $duration,
        int $status,
        array $details,
        ?int $excludeLeaveId = NULL
    ): array {
        $policy = $this->getPolicy();
        if (empty($policy) || (int) $policy['enabled'] !== 1) {
            throw new InvalidArgumentException('Urlop okolicznościowy nie jest skonfigurowany.');
        }
        if ((int) $policy['leave_type_id'] !== $leaveTypeId) {
            throw new InvalidArgumentException('Wniosek nie używa skonfigurowanego rodzaju urlopu okolicznościowego.');
        }
        if ($duration <= 0 || floor($duration) != $duration) {
            throw new InvalidArgumentException('Urlop okolicznościowy jest obsługiwany wyłącznie w pełnych dniach.');
        }
        if (!$this->isIsoDate($startDate) || !$this->isIsoDate($endDate) || $startDate > $endDate) {
            throw new InvalidArgumentException('Nieprawidłowy zakres dat urlopu okolicznościowego.');
        }

        $normalized = $this->normalizeDetails($details);
        $definition = self::EVENTS[$normalized['event_code']];
        $maxDays = (int) $definition['days'];
        if ($duration > $maxDays) {
            throw new InvalidArgumentException(
                'To zdarzenie uprawnia maksymalnie do ' . $this->formatDays((float) $maxDays) . ' ' .
                ($maxDays === 1 ? 'dnia' : 'dni') . ' zwolnienia.'
            );
        }

        if ($this->statusReservesLimit($status)) {
            $used = $this->getReservedUsage(
                $employeeId,
                $leaveTypeId,
                $normalized['event_code'],
                $normalized['event_date'],
                $excludeLeaveId
            );
            $remaining = max(0.0, $maxDays - $used);
            if ($duration > $remaining) {
                throw new InvalidArgumentException(
                    'Przekroczony limit dla tego zdarzenia. Pozostało: ' .
                    $this->formatDays($remaining) . ' ' . ($remaining == 1.0 ? 'dzień' : 'dni') . '.'
                );
            }
        }

        return $normalized;
    }

    public function setMetadata(int $leaveId, array $details): void
    {
        $normalized = $this->normalizeDetails($details);
        $data = [
            'leave_id' => $leaveId,
            'event_code' => $normalized['event_code'],
            'event_date' => $normalized['event_date'],
        ];

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

    private function getReservedUsage(
        int $employeeId,
        int $leaveTypeId,
        string $eventCode,
        string $eventDate,
        ?int $excludeLeaveId
    ): float {
        $this->db->select('COALESCE(SUM(l.duration), 0) AS used', FALSE);
        $this->db->from('leaves l');
        $this->db->join(self::META_TABLE . ' m', 'm.leave_id = l.id', 'inner');
        $this->db->where('l.employee', $employeeId);
        $this->db->where('l.type', $leaveTypeId);
        $this->db->where('m.event_code', $eventCode);
        $this->db->where('m.event_date', $eventDate);
        $this->db->where_in('l.status', [LMS_REQUESTED, LMS_ACCEPTED, LMS_CANCELLATION]);
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

    private function hasRecordedRequests(): bool
    {
        return $this->db->count_all_results(self::META_TABLE) > 0;
    }

    private function isIsoDate(string $value): bool
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        return $date !== FALSE && $date->format('Y-m-d') === $value;
    }

    private function formatDays(float $days): string
    {
        if (floor($days) == $days) {
            return (string) (int) $days;
        }
        return rtrim(rtrim(number_format($days, 3, '.', ''), '0'), '.');
    }
}
