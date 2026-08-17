<?php
/**
 * Central HAYNE registry describing the intended MVP behavior of leave types.
 *
 * Runtime business logic must resolve policies by leave_type_id from this
 * registry. Display names are never used to infer policy behavior.
 *
 * The MVP defaults are seeded only when the existing Jorani `types` catalog
 * matches the production-audited HAYNE signature (ID + acronym +
 * deduct_days_off). This prevents accidental mappings on an untouched or
 * differently configured upstream Jorani database.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_leave_type_registry_model extends CI_Model
{
    private const REGISTRY_TABLE = 'hayne_leave_type_registry';

    public function __construct()
    {
        $this->ensureSchema();
        $this->seedMvpDefaultsIfCatalogMatches();
    }

    public function ensureSchema(): void
    {
        if ($this->db->table_exists(self::REGISTRY_TABLE)) {
            return;
        }

        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `hayne_leave_type_registry` (
                `leave_type_id` int(11) NOT NULL,
                `policy_code` varchar(48) NOT NULL,
                `balance_mode` varchar(16) NOT NULL,
                `workflow_mode` varchar(16) NOT NULL,
                `privacy_mode` varchar(16) NOT NULL,
                `active_for_new_requests` tinyint(1) NOT NULL DEFAULT 1,
                `domain` varchar(16) NOT NULL DEFAULT 'LEAVE',
                `enabled` tinyint(1) NOT NULL DEFAULT 1,
                `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`leave_type_id`),
                UNIQUE KEY `policy_code` (`policy_code`),
                KEY `active_new_requests` (`enabled`, `active_for_new_requests`, `domain`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    /**
     * Canonical MVP map approved after the 2026-08-17 production audit.
     *
     * This array is a bootstrap definition only. Once persisted, runtime code
     * must read the registry table instead of relying on this array directly.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function mvpPolicies(): array
    {
        return [
            1 => self::policy(1, 'vacation', 'BALANCE', 'APPROVAL', 'STANDARD', TRUE, 'LEAVE'),
            2 => self::policy(2, 'on_demand_legacy', 'NONE', 'NONE', 'STANDARD', FALSE, 'LEAVE'),
            3 => self::policy(3, 'unpaid', 'NONE', 'APPROVAL', 'STANDARD', TRUE, 'LEAVE'),
            4 => self::policy(4, 'maternity', 'NONE', 'HR', 'SENSITIVE', TRUE, 'LEAVE'),
            5 => self::policy(5, 'parental', 'NONE', 'HR', 'SENSITIVE', TRUE, 'LEAVE'),
            6 => self::policy(6, 'paternity', 'NONE', 'HR', 'SENSITIVE', TRUE, 'LEAVE'),
            7 => self::policy(7, 'parental_childcare', 'NONE', 'HR', 'SENSITIVE', TRUE, 'LEAVE'),
            8 => self::policy(8, 'childcare', 'BALANCE', 'APPROVAL', 'STANDARD', TRUE, 'LEAVE'),
            9 => self::policy(9, 'caregiver', 'BALANCE', 'HR', 'SENSITIVE', TRUE, 'LEAVE'),
            10 => self::policy(10, 'sickness', 'NONE', 'HR', 'MEDICAL', TRUE, 'LEAVE'),
            11 => self::policy(11, 'family_sickness', 'NONE', 'HR', 'MEDICAL', TRUE, 'LEAVE'),
            12 => self::policy(12, 'force_majeure', 'BALANCE', 'APPROVAL', 'SENSITIVE', TRUE, 'LEAVE'),
            13 => self::policy(13, 'occasion', 'NONE', 'APPROVAL', 'STANDARD', TRUE, 'LEAVE'),
            14 => self::policy(14, 'blood_donation', 'NONE', 'HR', 'SENSITIVE', TRUE, 'LEAVE'),
            15 => self::policy(15, 'official_summons', 'NONE', 'HR', 'STANDARD', TRUE, 'LEAVE'),
            16 => self::policy(16, 'employer_day', 'NONE', 'HR', 'STANDARD', TRUE, 'LEAVE'),
            17 => self::policy(17, 'holiday_legacy', 'NONE', 'NONE', 'STANDARD', FALSE, 'LEAVE'),
            18 => self::policy(18, 'delegation_legacy', 'NONE', 'NONE', 'STANDARD', FALSE, 'LEAVE'),
            19 => self::policy(19, 'home_office', 'NONE', 'NONE', 'STANDARD', FALSE, 'WORK'),
            20 => self::policy(20, 'holiday_compensation', 'GRANT', 'APPROVAL', 'STANDARD', TRUE, 'LEAVE'),
        ];
    }

    /**
     * Production-audited type signature used only as a seeding guard.
     * Names are deliberately excluded: no runtime policy is inferred from UI
     * copy and terminal encoding cannot influence the mapping.
     *
     * @return array<int, array{acronym: string, deduct_days_off: int}>
     */
    public static function expectedCatalogSignature(): array
    {
        return [
            1 => ['acronym' => 'UW', 'deduct_days_off' => 0],
            2 => ['acronym' => 'UŻ', 'deduct_days_off' => 0],
            3 => ['acronym' => 'UB', 'deduct_days_off' => 0],
            4 => ['acronym' => 'UM', 'deduct_days_off' => 0],
            5 => ['acronym' => 'UR', 'deduct_days_off' => 0],
            6 => ['acronym' => 'UOjco', 'deduct_days_off' => 0],
            7 => ['acronym' => 'UWych', 'deduct_days_off' => 0],
            8 => ['acronym' => 'ODZ', 'deduct_days_off' => 0],
            9 => ['acronym' => 'UOpie', 'deduct_days_off' => 0],
            10 => ['acronym' => 'L4', 'deduct_days_off' => 0],
            11 => ['acronym' => 'O', 'deduct_days_off' => 0],
            12 => ['acronym' => 'SW', 'deduct_days_off' => 0],
            13 => ['acronym' => 'UO', 'deduct_days_off' => 0],
            14 => ['acronym' => 'K', 'deduct_days_off' => 0],
            15 => ['acronym' => 'WEZ', 'deduct_days_off' => 0],
            16 => ['acronym' => 'D', 'deduct_days_off' => 0],
            17 => ['acronym' => 'ODzS', 'deduct_days_off' => 0],
            18 => ['acronym' => 'ODzD', 'deduct_days_off' => 0],
            19 => ['acronym' => 'HO', 'deduct_days_off' => 0],
            20 => ['acronym' => 'DWS', 'deduct_days_off' => 1],
        ];
    }

    public function getPolicyForType(int $leaveTypeId): ?array
    {
        if ($leaveTypeId <= 0) {
            return NULL;
        }

        $row = $this->db
            ->where('leave_type_id', $leaveTypeId)
            ->get(self::REGISTRY_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    public function getPolicyByCode(string $policyCode): ?array
    {
        $policyCode = trim($policyCode);
        if ($policyCode === '') {
            return NULL;
        }

        $row = $this->db
            ->where('policy_code', $policyCode)
            ->get(self::REGISTRY_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPolicies(): array
    {
        return $this->db
            ->order_by('leave_type_id', 'asc')
            ->get(self::REGISTRY_TABLE)
            ->result_array();
    }

    /**
     * @return array<int, int>
     */
    public function getActiveLeaveTypeIdsForNewRequests(): array
    {
        $rows = $this->db
            ->select('leave_type_id')
            ->where('enabled', 1)
            ->where('active_for_new_requests', 1)
            ->where('domain', 'LEAVE')
            ->order_by('leave_type_id', 'asc')
            ->get(self::REGISTRY_TABLE)
            ->result_array();

        return array_map(static function (array $row): int {
            return (int) $row['leave_type_id'];
        }, $rows);
    }

    public function catalogMatchesMvpSignature(): bool
    {
        $expected = self::expectedCatalogSignature();
        $rows = $this->db
            ->select('id, acronym, deduct_days_off')
            ->where_in('id', array_keys($expected))
            ->order_by('id', 'asc')
            ->get('types')
            ->result_array();

        if (count($rows) !== count($expected)) {
            return FALSE;
        }

        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (!isset($expected[$id])) {
                return FALSE;
            }
            if ((string) $row['acronym'] !== $expected[$id]['acronym']) {
                return FALSE;
            }
            if ((int) $row['deduct_days_off'] !== $expected[$id]['deduct_days_off']) {
                return FALSE;
            }
        }

        return TRUE;
    }

    private function seedMvpDefaultsIfCatalogMatches(): void
    {
        if ($this->db->count_all_results(self::REGISTRY_TABLE) > 0) {
            return;
        }

        if (!$this->catalogMatchesMvpSignature()) {
            log_message(
                'error',
                'HAYNE leave type registry not seeded: types catalog does not match the audited MVP signature.'
            );
            return;
        }

        $this->db->trans_begin();
        foreach (self::mvpPolicies() as $policy) {
            $this->db->query(
                'INSERT IGNORE INTO `hayne_leave_type_registry` '
                . '(`leave_type_id`, `policy_code`, `balance_mode`, `workflow_mode`, `privacy_mode`, '
                . '`active_for_new_requests`, `domain`, `enabled`) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $policy['leave_type_id'],
                    $policy['policy_code'],
                    $policy['balance_mode'],
                    $policy['workflow_mode'],
                    $policy['privacy_mode'],
                    $policy['active_for_new_requests'],
                    $policy['domain'],
                    $policy['enabled'],
                ]
            );
        }

        $seededRows = $this->db->count_all_results(self::REGISTRY_TABLE);
        if ($this->db->trans_status() === FALSE || $seededRows !== count(self::mvpPolicies())) {
            $this->db->trans_rollback();
            log_message('error', 'HAYNE leave type registry seed failed and was rolled back.');
            return;
        }

        $this->db->trans_commit();
    }

    /**
     * @return array<string, mixed>
     */
    private static function policy(
        int $leaveTypeId,
        string $policyCode,
        string $balanceMode,
        string $workflowMode,
        string $privacyMode,
        bool $activeForNewRequests,
        string $domain
    ): array {
        return [
            'leave_type_id' => $leaveTypeId,
            'policy_code' => $policyCode,
            'balance_mode' => $balanceMode,
            'workflow_mode' => $workflowMode,
            'privacy_mode' => $privacyMode,
            'active_for_new_requests' => $activeForNewRequests ? 1 : 0,
            'domain' => $domain,
            'enabled' => 1,
        ];
    }
}
