# ABSENCE-POLICY-01 — Central MVP leave type registry

## Goal

Introduce one explicit HAYNE registry describing the approved MVP behavior of the 20 production leave types, without changing current request, balance, approval, visibility or day-count behavior in this PR.

## Production audit used as input

Read-only production checks on 2026-08-17 confirmed:

- leave types are numbered 1..20;
- `hayne_statutory_leave_policies` binds:
  - childcare -> 8,
  - caregiver -> 9,
  - force_majeure -> 12,
  - occasion -> 13,
  - official_summons -> 15,
  - holiday_compensation -> 20;
- requests currently exist only for types 1, 8, 9, 12 and 13;
- types 17, 18 and 19 have no request history;
- type 2 has no request history and HAYNE already represents on-demand leave as vacation type 1 plus request metadata;
- all 6 HAYNE leave profiles use vacation type 1 and 26 annual days;
- `contracts.default_leave_type` is 1;
- `excluded_types` is empty;
- `entitleddays` currently contains rows for types 1, 8, 9 and 12;
- production `types.deduct_days_off` is 0 for types 1..19 and 1 for type 20.

## Approved MVP decisions

- keep the solution simple and avoid a rule engine;
- central registry columns:
  - `leave_type_id`,
  - `policy_code`,
  - `balance_mode`,
  - `workflow_mode`,
  - `privacy_mode`,
  - `active_for_new_requests`,
  - `domain`,
  - `enabled`;
- do not migrate existing `entitleddays` in this PR;
- do not change existing Jorani `deduct_days_off` values in this PR;
- do not change existing request filtering/UI in this PR;
- target metadata marks types 2, 17, 18 and 19 inactive for future new-request selection, but that metadata is not consumed by runtime UI yet;
- type 19 is classified under domain `WORK`, not `LEAVE`;
- existing official summons credit exemption remains authoritative until a later migration explicitly switches behavior to the registry.

## Implementation

Added `Hayne_leave_type_registry_model` with:

- schema self-upgrade for persistent installations;
- canonical 20-row MVP bootstrap map;
- explicit lookup by leave type ID or policy code;
- helper returning IDs marked active for future new requests;
- production-catalog signature guard before automatic bootstrap.

The seed guard checks ID, acronym and `deduct_days_off`. It does not infer policy from translated/display names. If the catalog differs from the audited HAYNE catalog, the registry is left empty and an error is logged rather than guessing.

`Hayne_credit_exemption_model` loads the registry model in its constructor so persistent installations create/bootstrap the registry on a normal HAYNE statutory-request code path. `isCreditExemptType()` still delegates only to `isOfficialSummonsType()`, preserving existing runtime behavior.

Fresh MySQL installations create the table through `hayne/sql/003-leave-type-registry.sql`.

## Canonical MVP map

| ID | policy_code | balance | workflow | privacy | new requests | domain |
|---:|---|---|---|---|---:|---|
| 1 | vacation | BALANCE | APPROVAL | STANDARD | 1 | LEAVE |
| 2 | on_demand_legacy | NONE | NONE | STANDARD | 0 | LEAVE |
| 3 | unpaid | NONE | APPROVAL | STANDARD | 1 | LEAVE |
| 4 | maternity | NONE | HR | SENSITIVE | 1 | LEAVE |
| 5 | parental | NONE | HR | SENSITIVE | 1 | LEAVE |
| 6 | paternity | NONE | HR | SENSITIVE | 1 | LEAVE |
| 7 | parental_childcare | NONE | HR | SENSITIVE | 1 | LEAVE |
| 8 | childcare | BALANCE | APPROVAL | STANDARD | 1 | LEAVE |
| 9 | caregiver | BALANCE | HR | SENSITIVE | 1 | LEAVE |
| 10 | sickness | NONE | HR | MEDICAL | 1 | LEAVE |
| 11 | family_sickness | NONE | HR | MEDICAL | 1 | LEAVE |
| 12 | force_majeure | BALANCE | APPROVAL | SENSITIVE | 1 | LEAVE |
| 13 | occasion | NONE | APPROVAL | STANDARD | 1 | LEAVE |
| 14 | blood_donation | NONE | HR | SENSITIVE | 1 | LEAVE |
| 15 | official_summons | NONE | HR | STANDARD | 1 | LEAVE |
| 16 | employer_day | NONE | HR | STANDARD | 1 | LEAVE |
| 17 | holiday_legacy | NONE | NONE | STANDARD | 0 | LEAVE |
| 18 | delegation_legacy | NONE | NONE | STANDARD | 0 | LEAVE |
| 19 | home_office | NONE | NONE | STANDARD | 0 | WORK |
| 20 | holiday_compensation | GRANT | APPROVAL | STANDARD | 1 | LEAVE |

## Verification

Dedicated workflow `verify-pr-absence-policy-registry.yml` verifies:

1. registry source and SQL schema exist;
2. exactly 20 bootstrap policies are declared;
3. 2/17/18/19 are metadata-inactive and Home Office is in `WORK` domain;
4. no other runtime code consumes `getActiveLeaveTypeIdsForNewRequests()` yet;
5. existing `isCreditExemptType()` behavior remains official-summons-only;
6. cumulative overlay + patch stack still applies to Jorani v1.0.4;
7. application and MySQL images build;
8. final runtime PHP syntax passes;
9. fresh MySQL installation creates `hayne_leave_type_registry`.

## Explicitly out of scope

- hiding leave types 2/17/18/19 from the request form;
- changing any approval workflow;
- implementing L4/family sickness/parental/blood-donation MVP workflows;
- migrating balances;
- changing `deduct_days_off`;
- production database writes;
- production deployment.

## Next slice

ABSENCE-POLICY-02 should consume `active_for_new_requests` for the new-request type list and hide 2/17/18/19 while preserving history and existing records.
