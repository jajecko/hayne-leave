# PR-LEAVE-02 — Annual pools, automatic rollover and FIFO

## Goal

Add the smallest HAYNE-specific policy layer needed to stop re-entering annual vacation limits every year while keeping Jorani `entitleddays` as the granted-credit source of truth.

## Decisions

- HAYNE works in whole days only.
- HR enters the ready annual vacation dimension; HAYNE does not calculate 20/26 from seniority in this slice.
- One persistent employee profile stores:
  - vacation leave type,
  - annual number of days,
  - automatic yearly renewal switch,
  - first managed calendar year.
- Annual and carry-over credits are ordinary Jorani `entitleddays` rows tagged with machine-readable descriptions.
- Vacation usage is attributed virtually by FIFO: the oldest source year is consumed first.
- FIFO does not rewrite accepted leave requests and does not create a second leave balance engine.
- Rollover is lazy and idempotent: opening leave functionality or the limits administration initializes the required year once.

## Managed entitlement markers

- annual: `[HAYNE_POOL|annual|YYYY]`
- carry-over: `[HAYNE_POOL|carryover|SOURCE_YYYY]`

Every managed row is valid from `YYYY-01-01` through `YYYY-12-31`, so stock Jorani balance calculations continue to sum and deduct the same leave type normally.

## Persistence

New table:

`hayne_leave_profiles`

Fields:

- `employee_id` — primary key,
- `vacation_type_id`,
- `annual_days` — integer,
- `auto_renew`,
- `effective_from_year`,
- created/updated timestamps.

Fresh databases get the table from `hayne/sql/001-leave-profiles.sql`. Existing persistent databases self-upgrade through `CREATE TABLE IF NOT EXISTS` in `Hayne_leave_policy_model`.

## Annual initialization

Saving a profile immediately creates/updates the current annual managed entitlement.

For later years, the first HAYNE leave/balance/admin access:

1. reads the persistent profile,
2. rebuilds carry-over from the previous year's managed pools,
3. creates the new annual pool if missing,
4. uses the profile row as a database mutex to avoid duplicate creation.

## FIFO algorithm

For a selected calendar year:

1. load all managed vacation pools for the employee and year,
2. parse each pool's source year,
3. sort ascending by source year,
4. sum accepted and cancellation-pending vacation leave for that year,
5. allocate usage against the sorted pools until usage is exhausted,
6. expose granted / FIFO-used / remaining per source year.

Example:

- carry-over from 2025: 2,
- carry-over from 2026: 5,
- annual 2027: 26,
- accepted vacation in 2027: 4.

Result:

- 2025: 2 used, 0 remaining,
- 2026: 2 used, 3 remaining,
- 2027: 0 used, 26 remaining.

## Rollover

When initializing year N, HAYNE calculates the FIFO remainder of each source pool in N-1. Every positive whole-day remainder becomes a carry-over entitlement in N with the original source year preserved.

Rerunning initialization updates existing carry-over rows and removes stale rows instead of creating duplicates.

Fractional legacy balances are not silently rounded. They are skipped and logged because HAYNE v1 explicitly supports whole days only.

## UI

`Administracja → Urlopy → Limity urlopowe` now points to `/haynelimits`.

The page allows HR/admin to:

- select employee,
- select the vacation leave type,
- enter annual days,
- enable/disable automatic renewal,
- inspect a year,
- see FIFO breakdown by source year.

`Saldo urlopowe` also receives a compact FIFO breakdown when the employee has a HAYNE vacation profile.

## Guardrails

Not in this PR:

- legal seniority / education calculation for 20 vs 26 days,
- hourly or half-day leave,
- on-demand leave 4-day sublimit,
- child-care / force majeure / caregiver policy automation,
- changes to approval workflow,
- changes to leave statuses,
- production data repair/migration of existing manual entitlements.

If legacy/manual vacation entitlements coexist with HAYNE-managed entitlements of the same type/year, stock Jorani will still include them in its aggregate balance. Migration/cleanup of such legacy credits is an explicit operator task and is not silently performed here.

## Verification target

Dedicated workflow `verify-pr-leave-02` must prove:

- application builds,
- custom PHP files lint,
- `/haynelimits` is reachable as admin,
- profile save creates 26-day annual entitlement,
- five accepted days leave 21,
- next year gets carry-over 21 + annual 26,
- three next-year days consume carry-over first (18 remaining, annual untouched at 26),
- repeated yearly sync creates no duplicate pools,
- employee balance page renders the FIFO breakdown.
