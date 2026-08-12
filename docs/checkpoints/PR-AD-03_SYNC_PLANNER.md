# PR-AD-03 — guarded AD -> Jorani sync planner

## Scope

This slice introduces the first write-capable Active Directory synchronization engine, but keeps the default execution path strictly read-only.

It adds:

- stable `objectGUID -> users.id` identity mapping,
- deterministic AD/Jorani comparison and dry-run planning,
- explicit schema migration for existing installations,
- controlled initial provisioning of active employees,
- organization and position dictionary planning,
- two-pass manager assignment,
- deactivation/reactivation for already linked employees,
- a mass-change guard,
- a protected-local-login guard,
- a SHA256 plan confirmation gate before apply,
- CI self-tests and static destructive-operation guards.

It does **not** enable AD browser login and does not change leave balances, leave requests, roles of existing users, or historical leave data.

## Verified runtime baseline before implementation

PR-AD-01/02 runtime preview on QNAP passed against both domain controllers:

```text
HAYNE-SRV-AD01.hayne.pl | OK | USERS=45 | SHA256=baefc65b7beee399f34bf519521bbb4bb1c68bd8e2f1556ce69188262a2e0e4f
HAYNE-SRV-AD02.hayne.pl | OK | USERS=45 | SHA256=baefc65b7beee399f34bf519521bbb4bb1c68bd8e2f1556ce69188262a2e0e4f
PARITY: PASS
TOTAL: 45
ENABLED: 30
DISABLED: 15
```

Current Jorani `users` baseline is one local account only:

```text
id=4
login=jadmin
active=1
role=1
```

Current Jorani dictionaries:

- `organization`: only `id=0, LMS root`,
- `positions`: only `id=1, Employee`,
- `contracts`: `id=1, Global`,
- `roles`: `1=admin`, `2=user`, `8=HR admin`.

The current Jorani schema uses indexed integer relationship fields but has no physical MySQL foreign-key constraints on `users`.

## Stable identity

New table:

```text
hayne_ad_identity
```

Columns:

- `user_id` — primary key, Jorani user id,
- `object_guid` — unique immutable AD identity,
- `distinguished_name`,
- `last_seen_at`,
- `last_synced_at`,
- `source_dc`.

`objectGUID`, not login, is the authoritative identity. A later AD login rename therefore updates the linked Jorani user rather than creating a duplicate.

The migration is in:

```text
hayne/sql/002-ad-identity.sql
```

Fresh database volumes receive it through `docker/mysql/Dockerfile`. Existing installations must run the explicit guarded migration mode.

## Default mode: read-only plan

`/opt/hayne/ad-sync-plan.php` performs:

1. LDAPS query using the same Employee scope as PR-AD-01/02,
2. AD01/AD02 parity/failover evaluation,
3. read-only Jorani snapshot,
4. identity and login collision checks,
5. dictionary planning,
6. user action classification,
7. manager validation,
8. deterministic canonical plan hashing.

No argument means dry-run. JSON output is available with `--json`.

Expected user classification for the verified initial baseline, assuming no additional conflicts:

```text
CREATE_USER          30
SKIP_DISABLED_NEW    15
PRESERVE_LOCAL        1   # jadmin
DELETE                0
```

Dictionary action counts depend on the unique departments/titles used by the 30 active employees.

## Initial provisioning policy

For an AD Employee not linked in `hayne_ad_identity`:

- enabled + no local login collision -> `CREATE_USER`,
- disabled -> `SKIP_DISABLED_NEW`,
- same login already exists locally without objectGUID linkage -> blocker `LOCAL_LOGIN_CONFLICT`.

New users receive:

- `role=2` (`user`),
- `contract=1` (`Global`),
- `active=1`,
- AD first name / surname / login / mail / DN,
- organization mapped from AD `department`,
- position mapped from AD `title`,
- `identifier=''` because AD `employeeID` is not a confirmed business identifier,
- an unguessable random local password hash; AD browser login remains disabled.

Existing linked users keep their Jorani role. Their contract is not overwritten by routine sync updates.

## Existing linked users

For a user already mapped by objectGUID:

- AD enabled -> update AD-owned fields and reactivate if needed,
- AD disabled -> `active=0`,
- later AD re-enable -> `active=1`,
- login rename is allowed only when it does not collide with another Jorani account,
- role is never overwritten,
- no user is deleted.

A linked Jorani user missing from the current AD Employee scope is reported as `PRESERVE_MISSING_FROM_AD`; the planner does not infer termination from OU/scope absence.

## Break-glass local account

Default protected login:

```env
HAYNE_AD_PROTECTED_LOGINS=jadmin
```

`jadmin` is preserved as a local break-glass administrator. If a protected login ever appears in the AD Employee scope, apply is blocked rather than claiming the local account.

## Managers

Manager assignment is a second pass after users have been created/updated.

Rules:

- manager DN resolves through the AD Employee snapshot,
- active employee -> disabled manager is a blocker,
- active employee -> manager outside Employee scope is a blocker,
- missing manager is a warning and maps to `NULL`,
- self-manager resolution is refused.

The PR-AD-01/02 preview observed one employee with no manager (`maciej.kurys`) and two disabled employees whose manager DN points outside Employee scope. The new planner evaluates apply safety for the active sync population rather than guessing replacements.

## Dictionary quality

The sync never silently corrects AD department/title text.

Before apply it checks active-user dictionary values for:

- case/whitespace-only duplicates,
- near-duplicate department names,
- position names longer than Jorani `positions.name varchar(64)`,
- suspicious long alphanumeric tokens in titles.

Known source-data findings from the read-only preview include:

- `Dział Finansów i Reklamacji` vs `Dział Finasów i Reklamacji`,
- title variants differing by capitalization/double spaces,
- `Product Manager - Optometrysta RIZM1500055558`.

If a problematic value belongs to the active sync population, the planner marks it as a blocker. The source must be reviewed/corrected in AD; the sync does not auto-normalize it.

## Write gates

Default configuration remains:

```env
HAYNE_AD_APPLY_ENABLED=FALSE
HAYNE_AD_MAX_USER_CHANGES=50
HAYNE_AD_DEFAULT_ROLE_ID=2
HAYNE_AD_DEFAULT_CONTRACT_ID=1
HAYNE_AD_PROTECTED_LOGINS=jadmin
```

### Existing-installation migration

Schema creation is explicit and idempotent:

```bash
php /opt/hayne/ad-sync-plan.php --migrate --confirm=MIGRATE_HAYNE_AD_IDENTITY
```

It additionally requires:

```env
HAYNE_AD_APPLY_ENABLED=TRUE
```

After migration, disable the write gate again, rerun dry-run, review the new plan and obtain its new SHA256.

### Apply

Apply requires all of the following at the same time:

- `HAYNE_AD_SYNC_ENABLED=TRUE`,
- `HAYNE_AD_APPLY_ENABLED=TRUE`,
- no plan blockers,
- user mutation count <= `HAYNE_AD_MAX_USER_CHANGES`,
- exact confirmation token from the current dry-run:

```bash
php /opt/hayne/ad-sync-plan.php --apply --confirm=<PLAN_SHA256>
```

The tool rebuilds the plan immediately before applying it. A changed AD/DB state changes the SHA and causes the confirmation to fail.

## Destructive-operation policy

There is no user delete action and no SQL `DELETE FROM`, `TRUNCATE`, or `DROP TABLE` path in the planner.

Absence from AD scope is never treated as permission to delete or deactivate a user. Only an explicitly disabled AD account for an already linked Employee can deactivate that Jorani account.

## Idempotence

The self-test covers:

- active-new -> create,
- disabled-new -> skip,
- local `jadmin` -> preserve,
- dictionary planning,
- deterministic plan SHA,
- repeated post-sync planning -> zero user mutations,
- no generated delete action.

## QNAP deployment note

The running QNAP stack is deployment-specific and differs from the canonical repository Compose setup:

```text
compose file:       /share/Container/jorani/app/compose.yaml
application service: app
application container: app-app-1
application env:     app.env
application Dockerfile: Dockerfile.hayne-local
```

QNAP has no Git. Do not use `git pull` there and do not blindly replace the active `compose.yaml` or `Dockerfile.hayne-local` with repository files.

After merge, runtime deployment must manually mirror the new planner into the existing QNAP build path, preserve the existing MySQL volume, add the required environment variables to `app.env`, and validate **dry-run first**. No apply should be executed until the actual QNAP plan has been reviewed.

## Authentication boundary

Native Jorani LDAP login remains disabled:

```env
LDAP_ENABLED=FALSE
```

AD password authentication remains out of scope until the browser-to-Jorani endpoint is protected by HTTPS.
