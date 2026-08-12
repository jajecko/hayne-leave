# PR-DASH-02 — Live dashboard data

## Problem

The HAYNE dashboard deliberately shipped with em-dash placeholders in PR-DASH-01. As a result, a user could have a real vacation limit and leave requests in Jorani while Home still displayed no KPI values and always showed an empty upcoming-absence panel.

## Goal

Populate the existing dashboard from the same HAYNE/Jorani sources already used by leave administration and employee request pages. Do not introduce a parallel balance engine.

## Data sources

### Pozostało dni

Source: `Hayne_leave_policy_model::getYearSummary(employee, currentYear)`.

Displayed value: current HAYNE vacation pool `remaining` for the authenticated employee. This preserves the project's FIFO/carry-over accounting and the configured vacation type selected in `hayne_leave_profiles`.

### Wnioski oczekujące

Source: `Leaves_model::getLeavesOfEmployee(employee)`.

Displayed value: count of status `2` (`Requested`) for the authenticated employee.

### Zaplanowane nieobecności

Source: the same employee leave list.

Displayed value: count of future/current requests whose end date is today or later and whose status is either:

- `1` — Planned,
- `3` — Accepted.

Requested leaves remain represented by the separate pending KPI and are not double-counted as scheduled absences.

### Nadchodzące nieobecności

The same future Planned/Accepted set is sorted by start date and ID. The first three items are rendered with type, date range and status, linking to the existing Jorani leave detail route.

## Safety / behavior

- authenticated user ID always comes from the session;
- no cross-user data is queried;
- dashboard is read-only;
- no entitlement, pool or leave rows are created/updated from Home;
- failures are logged and the affected KPI falls back safely instead of breaking Home;
- no change to leave approval, status transitions, AD, calendar sync or DB schema.

## Acceptance

- user with configured HAYNE annual vacation pool sees numeric `Pozostało dni` instead of a permanent em dash;
- a Requested leave increments `Wnioski oczekujące`;
- future Planned/Accepted leaves increment `Zaplanowane nieobecności`;
- up to three future Planned/Accepted leaves appear in `Nadchodzące nieobecności`;
- no upcoming leave keeps the existing empty state;
- dashboard remains available for a user without a HAYNE vacation profile;
- existing dashboard locale wrappers and visual structure remain intact.
