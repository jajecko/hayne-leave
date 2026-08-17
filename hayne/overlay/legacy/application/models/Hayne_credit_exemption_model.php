<?php
/**
 * HAYNE adapter for Jorani entitlement-credit checks.
 *
 * The central leave-type registry is the primary policy source; runtime code never
 * guesses credit behavior from a translated/display name or a hard-coded production
 * leave type ID. The legacy official-summons mapping is retained only as a
 * compatibility fallback when the central registry has no row.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_credit_exemption_model extends CI_Model
{
    public const POLICY_CODE_OFFICIAL_SUMMONS = 'official_summons';

    private const POLICY_TABLE = 'hayne_statutory_leave_policies';
    private const BALANCE_MODE_REQUIRES_CREDIT = 'BALANCE';
    private const BALANCE_MODES_CREDIT_EXEMPT = ['NONE', 'GRANT'];

    public function __construct()
    {
        $this->ensureSchema();

        // ABSENCE-POLICY-03: the central registry is authoritative whenever a
        // row exists. The legacy statutory mapping remains a no-registry-row
        // compatibility fallback for older/persisted installations.
        $this->load->model('Hayne_leave_type_registry_model', 'hayne_leave_type_registry_model');
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

    /**
     * Return TRUE when native Jorani entitlement-credit validation must be
     * skipped for the given leave type.
     *
     * BALANCE means native credit is required. NONE and GRANT use another
     * policy mechanism and therefore must not be rejected only because the
     * Jorani entitlement balance is zero. Unknown/disabled registry rows fail
     * closed and keep native credit enforcement enabled.
     */
    public function isCreditExemptType(int $leaveTypeId): bool
    {
        if ($leaveTypeId <= 0) {
            return FALSE;
        }

        $policy = $this->hayne_leave_type_registry_model->getPolicyForType($leaveTypeId);
        if (!empty($policy)) {
            if ((int) ($policy['enabled'] ?? 0) !== 1) {
                return FALSE;
            }

            $balanceMode = strtoupper(trim((string) ($policy['balance_mode'] ?? '')));
            if ($balanceMode === self::BALANCE_MODE_REQUIRES_CREDIT) {
                return FALSE;
            }

            if (in_array($balanceMode, self::BALANCE_MODES_CREDIT_EXEMPT, TRUE)) {
                return TRUE;
            }

            log_message(
                'error',
                'Unknown HAYNE balance_mode for leave type #' . $leaveTypeId . ': ' . $balanceMode
            );
            return FALSE;
        }

        return $this->isOfficialSummonsType($leaveTypeId);
    }
}
