# PR-DASH-01 — Polish dashboard entry

Status: implementation branch
Date: 2026-08-11

## Root cause

The HAYNE dashboard existed only at `legacy/local/pages/en/home.php`.

Jorani `Pages::view()` selects the page by the authenticated user's `language_code` and first checks `local/pages/{language_code}/home.php`. Polish users therefore fell back to upstream `legacy/application/views/pages/pl/home.php`, which rendered the legacy "Leave and Overtime Management System" content instead of the HAYNE dashboard.

## Goal

Render the existing HAYNE dashboard for both Polish and English users without duplicating the dashboard markup.

## Changes

- move the dashboard markup into one shared local view: `legacy/local/pages/hayne-home.php`,
- make `legacy/local/pages/en/home.php` a thin wrapper requiring the shared view,
- add `legacy/local/pages/pl/home.php` as the same thin wrapper,
- add a dedicated CI workflow that logs in explicitly with `language=pl` and verifies the HAYNE dashboard is rendered.

## Guardrails

No changes to:

- dashboard data sources,
- leave balance calculations,
- leave request statuses,
- calendar feeds,
- authentication logic,
- session logic,
- database schema/data,
- shell/navigation styling,
- approval workflow.

This PR only fixes locale routing into the already implemented HAYNE dashboard presentation.

## Acceptance

- both `en/home.php` and `pl/home.php` require the same shared dashboard file,
- Polish login renders `data-hayne-home="v1"`,
- Polish login renders `Time off, in one place.`, KPI cards, `Nadchodzące nieobecności` and `Szybkie akcje`,
- Polish login does not render `Leave and Overtime Management System` or `Witamy w Jorani`,
- standard `verify` remains green,
- dedicated `verify-pr-dash-01` is green,
- screenshot artifact is visually reviewed before merge.

## Follow-up

`PR-DASH-02` will populate the currently placeholder KPI values and upcoming-absence section with real Jorani data. That data work is intentionally outside this PR.
