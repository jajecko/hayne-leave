<?php
/**
 * Explicit HAYNE mappings for leave types that must not consume an entitlement
 * balance. The mapping is persisted by leave_type_id; runtime code never
 * guesses a statutory leave from its translated/display name.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_credit_exemption_model extends CI_Model
{
    public const POLICY_CODE_OFFICIAL_SUMMONS = 'official_summons';

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';

    public function __construct()
    {
        $this->ensureSchema();
    }

    public function ensureSchema(): void
    {
        if ($this->db->table_exists(self::POLICY_TABLE)) {
            return;
        }

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

    public function getOfficialSummonsPolicy(): ?array
    {
        $row = $this->db
            ->where('policy_code', self::POLICY_CODE_OFFICIAL_SUMMONS)
            ->get(self::POLICY_TABLE)
            ->row_array();

        return empty($row) ? NULL : $row;
    }

    public function saveOfficialSummonsPolicy(int $leaveTypeId, bool $enabled): void
    {
        if ($leaveTypeId <= 0) {
            throw new InvalidArgumentException('Nieprawidłowy rodzaj zwolnienia na wezwanie organu.');
        }

        $existing = $this->getOfficialSummonsPolicy();
        $data = [
            'policy_code' => self::POLICY_CODE_OFFICIAL_SUMMONS,
            'leave_type_id' => $leaveTypeId,
            'enabled' => $enabled ? 1 : 0,
        ];

        if (empty($existing)) {
            $this->db->insert(self::POLICY_TABLE, $data);
        } else {
            $this->db
                ->where('policy_code', self::POLICY_CODE_OFFICIAL_SUMMONS)
                ->update(self::POLICY_TABLE, $data);
        }
    }

    public function isOfficialSummonsType(int $leaveTypeId): bool
    {
        if ($leaveTypeId <= 0) {
            return FALSE;
        }

        $policy = $this->getOfficialSummonsPolicy();
        return !empty($policy)
            && (int) $policy['enabled'] === 1
            && (int) $policy['leave_type_id'] === $leaveTypeId;
    }

    public function isCreditExemptType(int $leaveTypeId): bool
    {
        return $this->isOfficialSummonsType($leaveTypeId);
    }
}
