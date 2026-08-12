# PR-CAL-01 — Managed work calendar

## Goal

Ensure HAYNE Leave counts leave duration using the company's actual working calendar:

- Saturday = non-working day,
- Sunday = non-working day,
- Polish statutory public holiday = non-working day,
- leave calculation continues to use Jorani's existing `dayoffs` model and server-side duration recalculation.

No online request is made while an employee submits a leave request. The external holiday source is synchronized ahead of time into the local Jorani database.

## Existing Jorani behavior retained

Jorani already stores non-working days in `dayoffs` per contract. `Leaves::create()` reads the employee's contract dayoffs and recalculates the submitted duration on the server through `Leaves_model::actualLengthAndDaysOff()`.

PR-CAL-01 does not introduce a second leave-duration engine. It only maintains the input calendar used by the existing engine.

## Source of calendar data

- Weekends are generated locally and deterministically from the Gregorian calendar.
- Polish public holidays are fetched over HTTPS from Nager.Date.
- Default endpoint template: `https://date.nager.at/api/v4/Holidays/%s/%d` with country `PL`.
- The current civil year plus the next year are synchronized by default.

External data is fetched before any DB transaction. An upstream/network/schema error stops the run without modifying `dayoffs`.

## Safety model

Tool: `/opt/hayne/calendar-sync.php`

Default execution is READ ONLY:

```sh
php /opt/hayne/calendar-sync.php
```

Writes require both:

```text
--apply
HAYNE_CALENDAR_APPLY_ENABLED=TRUE
```

Additional controls:

- `HAYNE_CALENDAR_SYNC_ENABLED=TRUE` is required even for a plan.
- `HAYNE_CALENDAR_MAX_CHANGES` limits one run.
- configured contracts must exist before a plan is actionable.
- suspicious public-holiday counts are rejected.
- source URL must use HTTPS.
- only managed rows can be updated or deleted.

## Ownership boundary in `dayoffs`

Managed rows have titles beginning with:

```text
HAYNE-CALENDAR|
```

The synchronizer never updates or deletes rows outside that namespace.

If a manually maintained dayoff already exists for a required non-working date, the manual row wins. The synchronizer does not add a duplicate managed row for that date and removes any old managed duplicate.

This preserves administrator-defined exceptions and avoids double counting in Jorani.

## Managed values

All managed entries are full-day dayoffs (`type=1`).

Examples:

```text
HAYNE-CALENDAR|WEEKEND|Sobota
HAYNE-CALENDAR|WEEKEND|Niedziela
HAYNE-CALENDAR|HOLIDAY|Boże Narodzenie
```

If a statutory holiday falls on a weekend, the holiday entry wins for the title but the date remains one non-working day.

## Environment

```text
HAYNE_CALENDAR_SYNC_ENABLED=FALSE
HAYNE_CALENDAR_APPLY_ENABLED=FALSE
HAYNE_CALENDAR_CONTRACT_IDS=1
HAYNE_CALENDAR_COUNTRY_CODE=PL
HAYNE_CALENDAR_YEARS_AHEAD=1
HAYNE_CALENDAR_API_URL_TEMPLATE=https://date.nager.at/api/v4/Holidays/%s/%d
HAYNE_CALENDAR_HTTP_TIMEOUT=10
HAYNE_CALENDAR_MAX_CHANGES=500
```

Production activation is a separate deployment action. Do not replace the QNAP `app.env`, `compose.yaml`, or `Dockerfile.hayne-local` wholesale.

## QNAP deployment notes

The active QNAP image uses `/share/Container/jorani/app/Dockerfile.hayne-local`, not the repository Dockerfile. Deployment must make the same minimal tool-copy change there:

```dockerfile
COPY hayne/tools/calendar-sync.php /opt/hayne/calendar-sync.php
```

and include `/opt/hayne/calendar-sync.php` in the existing `chmod 0555` line.

The source file is downloaded from the merged GitHub commit into:

```text
/share/Container/jorani/app/hayne/tools/calendar-sync.php
```

Only the `app` service is rebuilt/recreated. MySQL is never rebuilt or restarted for this deployment.

## Activation sequence

1. Deploy the merged tool with calendar flags OFF.
2. Run `php /opt/hayne/calendar-sync.php --self-test` in the app container.
3. Set `HAYNE_CALENDAR_SYNC_ENABLED=TRUE`, keep APPLY disabled, recreate app only.
4. Run read-only plan and inspect counts.
5. Confirm leave-type `deduct_days_off` configuration before first write.
6. Enable `HAYNE_CALENDAR_APPLY_ENABLED=TRUE` and run one controlled `--apply`.
7. Run the plan again; expected DB changes = 0.
8. Smoke-test a leave range spanning a Saturday/Sunday and a public holiday.
9. Only after the controlled run is verified, schedule the same `--apply` command daily on QNAP.

## Scheduling model

Recommended production cadence: once daily. The scheduled command runs inside the existing app container, for example:

```sh
docker exec app-app-1 php /opt/hayne/calendar-sync.php --apply
```

The command is idempotent. On a normal day after initial synchronization it should report zero DB changes.

Do not call the external holiday API from a browser request or from `Leaves::create()`.

## Acceptance criteria

- PHP syntax check passes.
- deterministic `--self-test` passes without DB/network access.
- tool is present in the built image at `/opt/hayne/calendar-sync.php`.
- default config has calendar sync and calendar apply disabled.
- read-only mode performs no DB writes.
- only `HAYNE-CALENDAR|` rows are mutable by the tool.
- manual `dayoffs` are preserved.
- Saturday and Sunday are present as full-day non-working dates.
- API public holidays are present as full-day non-working dates.
- rerunning after apply is idempotent (0 changes).
- leave-duration smoke verifies weekends/holidays are excluded for leave types configured not to deduct dayoffs.
