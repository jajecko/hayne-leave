# PR-DASH-01 — Polish dashboard entry

Status: verified on PR branch
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

## Verification evidence

PR: #23

Verified head before this checkpoint update: `cfd53788a9ac8262113f70c4b2fd15b2f9a3393d`.

GitHub Actions:

- `verify` run #126 / `31484446610` — PASS,
- `verify-pr-dash-01` run #2 / `31484446600` — PASS.

The first dedicated run exposed only a CI host mismatch: the login smoke used `127.0.0.1` while Jorani redirected to the configured `localhost` base URL, dropping the authenticated curl session across the redirect. The workflow was corrected to use `localhost` consistently; no application/runtime code was changed for that fix.

Dedicated verification confirmed:

- shared dashboard source is present,
- English and Polish wrappers both require the same shared view,
- full HAYNE image build succeeds,
- built image contains the Polish local dashboard entry,
- login with `language=pl` reaches the HAYNE dashboard,
- rendered HTML contains `data-hayne-home="v1"`, hero, KPI cards, `Nadchodzące nieobecności` and `Szybkie akcje`,
- rendered HTML does not contain the legacy `Leave and Overtime Management System` / `Witamy w Jorani` content.

Visual review of `pr-dash-01-evidence` / `hayne-home-pl.png` confirmed:

- HAYNE sidebar and account shell remain intact,
- the central dashboard renders the intended hero and line-art illustration,
- all three KPI cards render in the expected row,
- `Nadchodzące nieobecności` and `Szybkie akcje` render below,
- the current em-dash KPI placeholders are expected and remain intentionally out of scope for this PR.

A final CI rerun is required after this checkpoint-only update before merge.

## Follow-up

`PR-DASH-02` will populate the currently placeholder KPI values and upcoming-absence section with real Jorani data. That data work is intentionally outside this PR.
