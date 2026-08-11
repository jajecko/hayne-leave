<?php
/**
 * HAYNE-specific annual leave profile and FIFO pool accounting.
 *
 * Jorani entitleddays remains the source of truth for granted leave credit.
 * This model only stores the persistent annual setting and creates idempotent,
 * tagged entitleddays rows for annual and carry-over vacation pools.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_leave_policy_model extends CI_Model
{
    private const PROFILE_TABLE = 'hayne_leave_profiles';
    private const POOL_PREFIX = '[HAYNE_POOL|';

    public function __construct()
    {
        $this->ensureSchema();
    }

    /**
     * Keep existing installations self-upgrading while the same table is also
     * created by the MySQL init script for fresh deployments.
     */
    public function ensureSchema(): void
    {
        if ($this->db->table_exists(self::PROFILE_TABLE)) {
            return;
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `hayne_leave_profiles` (
                `employee_id` int(11) NOT NULL,
                `vacation_type_id` int(11) NOT NULL,
                `annual_days` int(11) NOT NULL,
                `auto_renew` tinyint(1) NOT NULL DEFAULT 1,
                `effective_from_year` smallint(4) NOT NULL,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`employee_id`),
                KEY `vacation_type_id` (`vacation_type_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function getProfile(int $employeeId): ?array
    {
        $row = $this->db
            ->where('employee_id', $employeeId)
            ->get(self::PROFILE_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProfiles(): array
    {
        $this->db->select('hayne_leave_profiles.*');
        $this->db->select('users.firstname, users.lastname, users.active, users.contract');
        $this->db->select('types.name as vacation_type_name');
        $this->db->from(self::PROFILE_TABLE);
        $this->db->join('users', 'users.id = hayne_leave_profiles.employee_id');
        $this->db->join('types', 'types.id = hayne_leave_profiles.vacation_type_id');
        $this->db->order_by('users.lastname', 'asc');
        $this->db->order_by('users.firstname', 'asc');
        return $this->db->get()->result_array();
    }

    /**
     * Save the persistent annual setting. The current year's annual pool is
     * created/updated immediately; later years are generated lazily.
     */
    public function saveProfile(
        int $employeeId,
        int $vacationTypeId,
        int $annualDays,
        bool $autoRenew
    ): void {
        if ($annualDays < 0 || $annualDays > 366) {
            throw new InvalidArgumentException('Annual leave must be an integer between 0 and 366 days.');
        }

        $existing = $this->getProfile($employeeId);
        if (!empty($existing) && (int) $existing['vacation_type_id'] !== $vacationTypeId) {
            if ($this->hasManagedPools($employeeId)) {
                throw new InvalidArgumentException('Nie można zmienić rodzaju urlopu po utworzeniu pul HAYNE.');
            }
        }

        $currentYear = (int) date('Y');
        $data = [
            'employee_id' => $employeeId,
            'vacation_type_id' => $vacationTypeId,
            'annual_days' => $annualDays,
            'auto_renew' => $autoRenew ? 1 : 0,
            'effective_from_year' => empty($existing)
                ? $currentYear
                : (int) $existing['effective_from_year'],
        ];

        if (empty($existing)) {
            $this->db->insert(self::PROFILE_TABLE, $data);
        } else {
            $this->db->where('employee_id', $employeeId)->update(self::PROFILE_TABLE, $data);
        }

        // A setting changed during the year must affect the current annual pool.
        $this->upsertManagedPool(
            $employeeId,
            $vacationTypeId,
            $currentYear,
            'annual',
            $currentYear,
            $annualDays,
            TRUE
        );

        if ($autoRenew) {
            $this->syncCarryoverFromPreviousYear($employeeId, $vacationTypeId, $currentYear);
        }
    }

    public function ensureCurrentYear(int $employeeId): void
    {
        $this->ensureYear($employeeId, (int) date('Y'));
    }

    /**
     * Idempotently prepare one calendar year for an employee.
     *
     * Annual credit is generated from the persistent profile. Carry-over is
     * rebuilt from the previous year's managed pools after FIFO allocation of
     * accepted/cancellation-pending vacation leave.
     */
    public function ensureYear(int $employeeId, int $year): void
    {
        if ($year < 1970 || $year > 2100) {
            throw new InvalidArgumentException('Unsupported leave year.');
        }

        $profile = $this->getProfile($employeeId);
        if (empty($profile)) {
            return;
        }

        $effectiveYear = (int) $profile['effective_from_year'];
        $autoRenew = (int) $profile['auto_renew'] === 1;
        if ($year < $effectiveYear) {
            return;
        }
        if ($year > $effectiveYear && !$autoRenew) {
            return;
        }

        $this->db->trans_begin();
        // The unique profile row acts as a mutex, preventing duplicate pools
        // when two requests initialize the same year at the same time.
        $this->db->query(
            'SELECT employee_id FROM hayne_leave_profiles WHERE employee_id = ? FOR UPDATE',
            [$employeeId]
        );

        $vacationTypeId = (int) $profile['vacation_type_id'];

        if ($autoRenew && $year > $effectiveYear) {
            $this->syncCarryoverFromPreviousYear($employeeId, $vacationTypeId, $year);
        }

        if (!$this->managedPoolExists($employeeId, $vacationTypeId, $year, 'annual', $year)) {
            $this->upsertManagedPool(
                $employeeId,
                $vacationTypeId,
                $year,
                'annual',
                $year,
                (int) $profile['annual_days'],
                FALSE
            );
        }

        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            throw new RuntimeException('Could not initialize HAYNE leave pools.');
        }
        $this->db->trans_commit();
    }

    /**
     * Ensure all configured employees have pools for the selected year.
     */
    public function ensureYearForAll(int $year): void
    {
        foreach ($this->getProfiles() as $profile) {
            $this->ensureYear((int) $profile['employee_id'], $year);
        }
    }

    /**
     * FIFO breakdown for a calendar year. Oldest source year is consumed first.
     *
     * @return array<string, mixed>
     */
    public function getYearSummary(int $employeeId, int $year): array
    {
        $profile = $this->getProfile($employeeId);
        if (empty($profile)) {
            return [
                'configured' => FALSE,
                'year' => $year,
                'rows' => [],
                'granted' => 0,
                'used' => 0,
                'remaining' => 0,
                'unallocated_usage' => 0,
            ];
        }

        $vacationTypeId = (int) $profile['vacation_type_id'];
        $pools = $this->getManagedPoolsForYear($employeeId, $vacationTypeId, $year);
        $taken = $this->getVacationUsageForYear($employeeId, $vacationTypeId, $year);
        $rows = $this->allocateUsageFifo($pools, $taken);

        $granted = 0.0;
        $allocated = 0.0;
        $remaining = 0.0;
        foreach ($rows as $row) {
            $granted += (float) $row['granted'];
            $allocated += (float) $row['used'];
            $remaining += (float) $row['remaining'];
        }

        return [
            'configured' => TRUE,
            'year' => $year,
            'vacation_type_id' => $vacationTypeId,
            'annual_days' => (int) $profile['annual_days'],
            'auto_renew' => (int) $profile['auto_renew'] === 1,
            'rows' => $rows,
            'granted' => $granted,
            'used' => $taken,
            'remaining' => $granted - $taken,
            'unallocated_usage' => max(0, $taken - $allocated),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProfilesWithSummary(int $year): array
    {
        $rows = $this->getProfiles();
        foreach ($rows as &$row) {
            $row['summary'] = $this->getYearSummary((int) $row['employee_id'], $year);
        }
        unset($row);
        return $rows;
    }

    private function syncCarryoverFromPreviousYear(int $employeeId, int $vacationTypeId, int $targetYear): void
    {
        $previousYear = $targetYear - 1;
        $previousPools = $this->getManagedPoolsForYear($employeeId, $vacationTypeId, $previousYear);
        if (empty($previousPools)) {
            return;
        }

        $previousUsage = $this->getVacationUsageForYear($employeeId, $vacationTypeId, $previousYear);
        $previousRows = $this->allocateUsageFifo($previousPools, $previousUsage);
        $expectedSources = [];

        foreach ($previousRows as $row) {
            $remaining = (float) $row['remaining'];
            $sourceYear = (int) $row['source_year'];
            if ($remaining <= 0) {
                continue;
            }

            // HAYNE v1 stores only whole-day pools. Refuse silent rounding of
            // any legacy fractional history and leave an explicit log instead.
            if (floor($remaining) != $remaining) {
                log_message(
                    'error',
                    'HAYNE rollover skipped fractional balance for employee #' . $employeeId .
                    ', source year ' . $sourceYear . ': ' . $remaining
                );
                continue;
            }

            $expectedSources[$sourceYear] = (int) $remaining;
            $this->upsertManagedPool(
                $employeeId,
                $vacationTypeId,
                $targetYear,
                'carryover',
                $sourceYear,
                (int) $remaining,
                TRUE
            );
        }

        // Recalculation is idempotent: stale carry-over markers disappear if
        // the previous year's corrected usage exhausted that source pool.
        foreach ($this->getManagedPoolsForYear($employeeId, $vacationTypeId, $targetYear) as $pool) {
            if ($pool['kind'] !== 'carryover') {
                continue;
            }
            $sourceYear = (int) $pool['source_year'];
            if (!array_key_exists($sourceYear, $expectedSources)) {
                $this->db->where('id', (int) $pool['id'])->delete('entitleddays');
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getManagedPoolsForYear(int $employeeId, int $vacationTypeId, int $year): array
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $this->db->select('id, days, description, startdate, enddate');
        $this->db->from('entitleddays');
        $this->db->where('employee', $employeeId);
        $this->db->where('type', $vacationTypeId);
        $this->db->where('startdate', $startDate);
        $this->db->where('enddate', $endDate);
        $this->db->like('description', self::POOL_PREFIX, 'after');
        $records = $this->db->get()->result_array();

        $pools = [];
        foreach ($records as $record) {
            $marker = $this->parsePoolMarker((string) $record['description']);
            if ($marker === NULL) {
                continue;
            }
            $pools[] = [
                'id' => (int) $record['id'],
                'kind' => $marker['kind'],
                'source_year' => $marker['source_year'],
                'granted' => (float) $record['days'],
            ];
        }

        usort($pools, static function (array $a, array $b): int {
            if ($a['source_year'] === $b['source_year']) {
                if ($a['kind'] === $b['kind']) {
                    return 0;
                }
                return $a['kind'] === 'carryover' ? -1 : 1;
            }
            return $a['source_year'] <=> $b['source_year'];
        });

        return $pools;
    }

    private function getVacationUsageForYear(int $employeeId, int $vacationTypeId, int $year): float
    {
        $startDate = sprintf('%04d-01-01', $year);
        $endDate = sprintf('%04d-12-31', $year);

        $this->db->select('COALESCE(SUM(duration), 0) AS taken', FALSE);
        $this->db->from('leaves');
        $this->db->where('employee', $employeeId);
        $this->db->where('type', $vacationTypeId);
        $this->db->where_in('status', [LMS_ACCEPTED, LMS_CANCELLATION]);
        $this->db->where('startdate >=', $startDate);
        $this->db->where('enddate <=', $endDate);
        $row = $this->db->get()->row_array();

        return max(0.0, (float) ($row['taken'] ?? 0));
    }

    /**
     * @param array<int, array<string, mixed>> $pools
     * @return array<int, array<string, mixed>>
     */
    private function allocateUsageFifo(array $pools, float $taken): array
    {
        $remainingUsage = max(0.0, $taken);
        $result = [];

        foreach ($pools as $pool) {
            $granted = max(0.0, (float) $pool['granted']);
            $used = min($granted, $remainingUsage);
            $remainingUsage -= $used;

            $pool['used'] = $used;
            $pool['remaining'] = $granted - $used;
            $result[] = $pool;
        }

        return $result;
    }

    private function managedPoolExists(
        int $employeeId,
        int $vacationTypeId,
        int $targetYear,
        string $kind,
        int $sourceYear
    ): bool {
        $marker = $this->poolMarker($kind, $sourceYear);
        $startDate = sprintf('%04d-01-01', $targetYear);
        $endDate = sprintf('%04d-12-31', $targetYear);

        return $this->db
            ->where('employee', $employeeId)
            ->where('type', $vacationTypeId)
            ->where('startdate', $startDate)
            ->where('enddate', $endDate)
            ->where('description', $marker)
            ->count_all_results('entitleddays') > 0;
    }

    private function upsertManagedPool(
        int $employeeId,
        int $vacationTypeId,
        int $targetYear,
        string $kind,
        int $sourceYear,
        int $days,
        bool $replaceDays
    ): void {
        $marker = $this->poolMarker($kind, $sourceYear);
        $startDate = sprintf('%04d-01-01', $targetYear);
        $endDate = sprintf('%04d-12-31', $targetYear);

        $matches = $this->db
            ->select('id, days')
            ->where('employee', $employeeId)
            ->where('type', $vacationTypeId)
            ->where('startdate', $startDate)
            ->where('enddate', $endDate)
            ->where('description', $marker)
            ->order_by('id', 'asc')
            ->get('entitleddays')
            ->result_array();

        if (empty($matches)) {
            $this->db->insert('entitleddays', [
                'employee' => $employeeId,
                'contract' => NULL,
                'overtime' => NULL,
                'startdate' => $startDate,
                'enddate' => $endDate,
                'type' => $vacationTypeId,
                'days' => $days,
                'description' => $marker,
            ]);
            return;
        }

        $primaryId = (int) $matches[0]['id'];
        if ($replaceDays && (float) $matches[0]['days'] !== (float) $days) {
            $this->db->where('id', $primaryId)->update('entitleddays', ['days' => $days]);
        }

        // Heal any duplicate marker rows left by an interrupted/racing older run.
        if (count($matches) > 1) {
            $duplicateIds = array_map(
                static fn(array $row): int => (int) $row['id'],
                array_slice($matches, 1)
            );
            $this->db->where_in('id', $duplicateIds)->delete('entitleddays');
        }
    }

    private function hasManagedPools(int $employeeId): bool
    {
        $this->db->from('entitleddays');
        $this->db->where('employee', $employeeId);
        $this->db->like('description', self::POOL_PREFIX, 'after');
        return $this->db->count_all_results() > 0;
    }

    private function poolMarker(string $kind, int $sourceYear): string
    {
        if (!in_array($kind, ['annual', 'carryover'], TRUE)) {
            throw new InvalidArgumentException('Unsupported HAYNE pool kind.');
        }
        return sprintf('[HAYNE_POOL|%s|%04d]', $kind, $sourceYear);
    }

    /**
     * @return array{kind:string, source_year:int}|null
     */
    private function parsePoolMarker(string $description): ?array
    {
        if (!preg_match('/^\[HAYNE_POOL\|(annual|carryover)\|(\d{4})\]$/', $description, $matches)) {
            return NULL;
        }
        return [
            'kind' => $matches[1],
            'source_year' => (int) $matches[2],
        ];
    }
}
