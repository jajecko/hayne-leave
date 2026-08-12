# PR-AD-03 runtime acceptance expectations

This file is intentionally separate from the implementation checkpoint so the first QNAP dry-run can be compared against a frozen acceptance target.

## Required AD baseline

The planner should see the already verified directory baseline:

```text
AD01 users: 45
AD02 users: 45
AD01/AD02 identity hash: identical
Employee enabled: 30
Employee disabled: 15
```

If those counts or identity hashes change before runtime validation, the change must be reviewed rather than silently accepted as the old baseline.

## Required initial Jorani baseline

Before any provisioning apply, the current Jorani baseline is expected to remain:

```text
users: 1
jadmin: id=4, active=1, role=1
```

## Required user-action classification

With that baseline and before any new local user is added, the user classification should be:

```text
CREATE_USER:       30
SKIP_DISABLED_NEW: 15
PRESERVE_LOCAL:     1  # jadmin
DELETE:             0
```

Dictionary-create counts are intentionally not frozen here because they depend on unique department/title values among the 30 active Employees only.

## Expected first-run blockers

Before migration, `MIGRATION_REQUIRED` is expected.

Additional data-quality blockers may be produced for active employees. Known candidates from PR-AD-01/02 preview are:

- near-duplicate department names (`Dział Finansów i Reklamacji` vs `Dział Finasów i Reklamacji`),
- capitalization/whitespace-equivalent title variants,
- suspicious title token in `Product Manager - Optometrysta RIZM1500055558`.

Whether each becomes a blocker depends on whether the affected AD record belongs to the active 30-user provisioning population.

## Safety acceptance

Before any apply:

- `jadmin` must remain preserved,
- no local account may be automatically adopted by login,
- no user delete operation may exist,
- no user absent from AD scope may be auto-deactivated,
- plan SHA must be reviewed after the identity-table migration,
- `HAYNE_AD_APPLY_ENABLED` stays `FALSE` during dry-run review.
