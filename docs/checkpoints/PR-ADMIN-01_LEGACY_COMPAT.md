# PR-ADMIN-01 — Legacy admin compatibility

Status: verified
Date: 2026-08-11

## Reported regressions

1. Editing leave type `id=0` rendered PHP 8 warnings in `leavetypes/edit.php`:
   - `Undefined array key "name"`,
   - `Undefined array key "acronym"`,
   - `Undefined array key "deduct_days_off"`.
2. Legacy admin/HR controls, especially the employee list toolbar, had broken/tight button grouping.
3. Form field spacing was too tight on legacy screens.
4. The same failure classes needed to be checked across all primary authenticated surfaces, not only the screenshots that exposed them.

## Root causes

### Leave type zero

Upstream Jorani v1.0.4 defines `Types_model::getTypes(int $id = 0)` and treats `0` as the sentinel for "return all types". Leave type `id=0` is also a real database row (`compensate`). Therefore `/leavetypes/edit/0` received a list of rows instead of one row and PHP 8.5 reported missing array keys in the edit view.

### Legacy control spacing

HAYNE `foundation.css` intentionally reset form inputs and buttons, including `margin-bottom: 0` on controls and `margin: 0` on buttons. Bootstrap 2-era Jorani admin/HR views rely on legacy `control-group`, `input-append`, `input-prepend` and `btn-group` spacing/layout. The reset was too aggressive for those untouched legacy views.

## Changes

- added `160-types-model-zero-id.patch`:
  - changed the `getTypes()` list sentinel from integer `0` to `null`,
  - preserved all existing no-argument list calls,
  - allowed the real leave type with `id=0` to be fetched as one record.
- added `legacy-compat.css`, loaded after `foundation.css` and before page-specific HAYNE styles:
  - restored spacing for `.form-horizontal .control-group`,
  - restored vertical rhythm in modal forms,
  - normalized `input-append` / `input-prepend`,
  - normalized legacy `btn-group` rendering,
  - aligned icons and button labels,
  - added spacing between adjacent action buttons,
  - made user-table row actions stable touch/click targets,
  - kept mobile wrapping resilient.
- added `verify-admin-surfaces.yml` to crawl the main authenticated user/admin/HR routes and fail if PHP warnings/errors appear in rendered HTML.

## Automated surface audit

The workflow checks the following routes using an authenticated fixture user promoted to admin + HR in the disposable CI database:

- `/home`
- `/leaves`
- `/leaves/create`
- `/leaves/counters`
- `/calendar/individual`
- `/requests`
- `/users`
- `/users/create`
- `/users/edit/1`
- `/leavetypes`
- `/leavetypes/create`
- `/leavetypes/edit/0`
- `/leavetypes/edit/1`
- `/hr/employees`
- `/organization`
- `/contracts`
- `/contracts/create`
- `/positions`
- `/positions/create`
- `/admin/diagnostic`
- `/admin/settings`

Each response is scanned for rendered PHP warnings/notices/deprecations and representative legacy screens are captured as screenshots.

## Verification

Initial verified head: `c3f659eb7bda52fa47d4197f157677bfee069fa6`.

- `verify` run #131 — PASS.
- `verify-admin-surfaces` run #1 — PASS.
- all 21 audited routes returned non-empty responses without rendered `A PHP Error was encountered`, `Undefined array key`, `Undefined variable`, Warning, Notice or Deprecated output.
- `/leavetypes/edit/0` rendered:
  - `name="id" value="0"`,
  - `name="name" id="name" value="compensate"`,
  - a valid acronym field,
  - no PHP warning block.
- representative screenshots reviewed:
  - users list,
  - user create,
  - user edit,
  - HR employee list,
  - leave types,
  - contracts,
  - positions,
  - admin settings.
- visual review confirmed restored field rhythm and separated action buttons/toolbars. The file-based screenshots may omit Material Design icon glyphs, but control geometry and spacing are valid.

## Guardrails

No changes to:

- leave type business rules or IDs,
- existing leave requests,
- leave balance calculations,
- approval statuses/workflow,
- authentication/session behavior,
- production data,
- database schema,
- HAYNE page-specific layouts already rebuilt in dedicated UI PRs.

## Acceptance

- `/leavetypes/edit/0` renders a valid edit form with `name=compensate`, without PHP warnings — PASS,
- `/leavetypes/edit/1` remains valid — PASS,
- employee/user list controls no longer visually collapse into each other — PASS,
- legacy forms regain consistent vertical spacing — PASS,
- all audited primary routes return non-empty responses without rendered PHP warnings/errors — PASS,
- standard `verify` remains green — PASS,
- `verify-admin-surfaces` is green — PASS,
- representative screenshots are visually reviewed before merge — PASS.
