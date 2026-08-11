# PR-ADMIN-01 — Legacy admin compatibility

Status: implementation branch
Date: 2026-08-11

## Reported regressions

1. Editing leave type `id=0` renders PHP 8 warnings in `leavetypes/edit.php`:
   - `Undefined array key "name"`,
   - `Undefined array key "acronym"`,
   - `Undefined array key "deduct_days_off"`.
2. Legacy admin/HR controls, especially the employee list toolbar, have broken/tight button grouping.
3. Form field spacing is too tight on legacy screens.
4. The same failure classes need to be checked across all primary authenticated surfaces, not only the screenshots that exposed them.

## Root causes

### Leave type zero

Upstream Jorani v1.0.4 defines `Types_model::getTypes(int $id = 0)` and treats `0` as the sentinel for "return all types". Leave type `id=0` is also a real database row (`compensate`). Therefore `/leavetypes/edit/0` receives a list of rows instead of one row and PHP 8.5 reports missing array keys in the edit view.

### Legacy control spacing

HAYNE `foundation.css` intentionally reset form inputs and buttons, including `margin-bottom: 0` on controls and `margin: 0` on buttons. Bootstrap 2-era Jorani admin/HR views rely on legacy `control-group`, `input-append`, `input-prepend` and `btn-group` spacing/layout. The reset is too aggressive for those untouched legacy views.

## Changes

- add `160-types-model-zero-id.patch`:
  - change the `getTypes()` list sentinel from integer `0` to `null`,
  - preserve all existing no-argument list calls,
  - allow the real leave type with `id=0` to be fetched as one record.
- add `legacy-compat.css`, loaded after `foundation.css` and before page-specific HAYNE styles:
  - restore spacing for `.form-horizontal .control-group`,
  - restore vertical rhythm in modal forms,
  - normalize `input-append` / `input-prepend`,
  - normalize legacy `btn-group` rendering,
  - align icons and button labels,
  - add spacing between adjacent action buttons,
  - make user-table row actions stable touch/click targets,
  - keep mobile wrapping resilient.
- add `verify-admin-surfaces.yml` to crawl the main authenticated user/admin/HR routes and fail if PHP warnings/errors appear in rendered HTML.

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

- `/leavetypes/edit/0` renders a valid edit form with `name=compensate`, without PHP warnings,
- `/leavetypes/edit/1` remains valid,
- employee/user list controls no longer visually collapse into each other,
- legacy forms regain consistent vertical spacing,
- all audited primary routes return non-empty responses without rendered PHP warnings/errors,
- standard `verify` remains green,
- `verify-admin-surfaces` is green,
- representative screenshots are visually reviewed before merge.
