# PR-LEAVE-TYPES-01 — HAYNE leave types without compensate

## Scope

- Keep HAYNE leave types as ordinary positive identifiers starting at `1`.
- Treat `1` as the default leave type (`urlop wypoczynkowy`) unless a valid contract-specific type overrides it.
- Remove Jorani's implicit business dependency on leave type `0 = compensate`.
- Prevent `/leaves/create` from crashing when a configured/default leave type no longer exists.
- Disable overtime by default in HAYNE example configuration.

## Production data migration already performed manually

On the QNAP production database the leave type table was rebuilt before this PR:

- `0 = compensate` removed.
- previous IDs `6..24` remapped to `1..19`.
- `1 = urlop wypoczynkowy`.
- `contracts.default_leave_type` for `Global` changed from `6` to `1`.
- `types.AUTO_INCREMENT = 20`.

Before the migration the following referencing tables were confirmed empty:

- `leaves`
- `entitleddays`
- `excluded_types`
- `overtime`

The production database is not modified by this PR.

## Runtime changes

`hayne/patches/170-leave-types-no-compensate.patch`:

- parses `DEFAULT_LEAVE_TYPE` with `env_int`, defaulting to `1`;
- adds optional `OVERTIME_COMPENSATION_TYPE`, default `0` meaning no overtime entitlement credit;
- resolves available leave types for the employee contract before computing credit;
- falls back to the first available type when contract/config/selected type is stale;
- safely returns zero credit if no leave types exist;
- replaces hard-coded overtime entitlement `type = 0` with an explicitly configured positive type;
- allows normal deletion semantics for any unused leave type instead of reserving identifier `0`.

`.env.example` now sets:

- `DEFAULT_LEAVE_TYPE=1`
- `DISABLE_OVERTIME=TRUE`
- `OVERTIME_COMPENSATION_TYPE=0`

## Regression coverage

`.github/workflows/verify-pr-leave-types-01.yml` builds HAYNE Leave and then creates a disposable database fixture matching the new HAYNE numbering:

- types `1..3`, no type `0`;
- Global contract default `1`;
- Polish admin user on the Global contract.

It verifies:

1. `/leaves/create` renders without PHP warnings or TypeErrors;
2. type `1` (`urlop wypoczynkowy`) is selected by default;
3. `compensate` is absent;
4. changing `contracts.default_leave_type` to stale identifier `999` still renders `/leaves/create` safely and falls back to the first available type;
5. the built `Overtime_model` no longer contains hard-coded entitlement `type => 0`.

## Out of scope

- production DB writes;
- leave balance business rules;
- UI/button standardization;
- login redirect to dashboard;
- new HAYNE Leave logo;
- historical leave migration (there was no historical data in the referencing tables at migration time).
