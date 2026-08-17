# ABSENCE-POLICY-03 — registry balance mode as credit policy

Status: implementation checkpoint
Date: 2026-08-17

## Goal

Make `hayne_leave_type_registry.balance_mode` the primary source for deciding whether native Jorani entitlement-credit validation applies to a leave type.

The existing PR #72 integration points already call `Hayne_credit_exemption_model::isCreditExemptType()` from:

- leave creation,
- leave editing,
- AJAX `leaves/validate` used by the request form.

This slice therefore changes one policy adapter instead of adding type-specific conditions to those three runtime paths.

## Runtime rule

For an enabled central-registry row:

- `BALANCE` — native Jorani entitlement credit is required;
- `NONE` — native Jorani entitlement credit is not applicable;
- `GRANT` — native Jorani entitlement credit is not applicable because a dedicated grant mechanism is authoritative.

Unknown `balance_mode` values and disabled registry rows fail closed: native credit remains required.

If the central registry has no row for the leave type, the previous explicit `official_summons` mapping is retained as a compatibility fallback. The fallback is not consulted when a central-registry row exists.

## MVP effect

The current 20-row registry contains:

- `BALANCE`: IDs 1, 8, 9, 12;
- `NONE`: 15 types;
- `GRANT`: ID 20.

After the ABSENCE-POLICY-02 new-request filter there are 16 active `LEAVE` types. Four of them use `BALANCE`; the remaining 12 use `NONE` or `GRANT` and therefore must not be rejected merely because the generic Jorani credit is zero.

Examples now covered centrally include unpaid leave, maternity/parental/paternity/parental-childcare, sickness records, occasion leave, blood donation, official summons, employer-granted day and holiday compensation. Their own type-specific eligibility/workflow validators remain separate and unchanged.

## Compatibility and safety

- No policy is inferred from a translated/display name.
- No production leave-type ID is hard-coded in the credit adapter.
- Existing `DISALLOW_REQUESTS_WITHOUT_CREDIT` stays globally enabled.
- Existing vacation/childcare/caregiver/force-majeure credit-backed policies remain protected by native credit validation.
- Type 20 still uses the HAYNE holiday-compensation grant mechanism; this slice only prevents an unrelated Jorani entitlement balance from blocking it.
- Legacy official-summons storage/admin remains in place as compatibility debt; it is not removed or repurposed here.

## Explicit non-goals

- no `entitleddays` migration or cleanup;
- no changes to `types.deduct_days_off`;
- no workflow-mode enforcement;
- no privacy-mode enforcement;
- no calendar/day-count changes;
- no changes to statutory pool calculations;
- no Home Office module;
- no changes to the 20-row registry map;
- no production deployment in this PR.

## Acceptance criteria

1. `isCreditExemptType()` reads the persisted central registry by `leave_type_id` first.
2. Enabled `BALANCE` rows return non-exempt; enabled `NONE`/`GRANT` rows return exempt.
3. Disabled/unknown rows fail closed and still require native credit.
4. A missing registry row falls back to the pre-existing explicit official-summons mapping.
5. PR #72 create/edit/validate integration points remain unchanged and all use the same adapter.
6. Exactly four MVP policies require native credit: 1, 8, 9 and 12.
7. Exactly 12 active new-request `LEAVE` policies are credit-exempt through `NONE`/`GRANT`.
8. Full overlay + patch stack still applies to Jorani v1.0.4, final PHP lints and the Docker image builds.
