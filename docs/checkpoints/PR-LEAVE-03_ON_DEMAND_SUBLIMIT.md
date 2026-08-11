# PR-LEAVE-03 — Urlop na żądanie as a four-day vacation sublimit

## Goal

Implement the simplest correct HAYNE representation of Polish `urlop na żądanie` for the whole-day-only product scope:

- maximum 4 days in a calendar year,
- no separate entitlement,
- every on-demand day consumes the employee's ordinary vacation type and therefore the same HAYNE/Jorani vacation balance and FIFO pools.

## Data model

New table: `hayne_leave_request_meta`.

Fields:

- `leave_id` — primary key referencing the Jorani leave request identifier logically,
- `on_demand` — boolean marker,
- created/updated timestamps.

There is intentionally no separate `entitleddays` row for on-demand leave.

A request marked on-demand is still persisted in `leaves.type` as the employee's configured vacation type from `hayne_leave_profiles.vacation_type_id`.

## Why metadata instead of a new leave type

A separate Jorani leave type would create a separate credit balance. That would incorrectly make the four days additional to annual vacation or require fragile double deduction.

Request metadata preserves the business distinction while leaving accounting in one vacation pool.

## Limit semantics

Constant: `4 days / calendar year`.

The sublimit is reserved by requests in these Jorani statuses:

- Requested,
- Accepted,
- Cancellation pending.

It is not reserved by:

- Planned,
- Rejected,
- Canceled.

A planned on-demand request is allowed, but converting that plan to Requested revalidates the four-day limit.

## Concurrency

On-demand creation/edit/plan submission locks the employee's `hayne_leave_profiles` row in a database transaction before checking the limit. This serializes simultaneous requests for one employee and prevents two concurrent submissions from both seeing the same remaining sublimit.

## Validation

Server-side validation requires:

- employee has a HAYNE vacation profile,
- selected Jorani leave type equals the configured vacation type,
- duration is a positive whole number of days,
- one on-demand request stays inside one calendar year,
- single request is at most 4 days,
- reserved on-demand usage plus the request does not exceed 4 days.

UI checks are advisory only; backend validation is authoritative.

## UI

On the request form, when the configured vacation type is selected, HAYNE shows:

`Urlop na żądanie`

with a checkbox and current annual sublimit information. Selecting another leave type hides and clears the checkbox.

The copy explicitly says that on-demand leave does not add days and consumes ordinary vacation.

The balance page shows:

`Urlop na żądanie: X / 4 dni wykorzystane lub oczekujące`

and remaining sublimit.

My Requests, request detail and manager approvals decorate marked rows as `Urlop na żądanie` while preserving the underlying vacation `type` ID.

## Interaction with FIFO

PR-LEAVE-02 remains authoritative for vacation pool allocation.

Example:

- carry-over 2025: 2 days,
- current 2026: 26 days,
- on-demand request: 1 day.

The request increments the on-demand sublimit from 0/4 to 1/4 and, as ordinary vacation usage, PR-LEAVE-02 FIFO attributes that day to the oldest available vacation pool first.

## Guardrails / not in scope

- no hours or half-days,
- no extra four-day entitlement,
- no automatic legal seniority calculation,
- no change to approval workflow,
- no change to Jorani leave status meanings,
- no separate vacation type required for on-demand leave,
- no attempt to infer on-demand status from free-text cause/comments.

## Verification target

Dedicated `verify-pr-leave-03` must prove:

1. 26-day vacation profile can be configured.
2. Request form exposes on-demand only against the configured vacation type.
3. A 3-day Requested on-demand request is saved with ordinary vacation type and metadata marker.
4. A subsequent 2-day request is rejected because it would exceed 4.
5. A subsequent 1-day request is accepted, reaching 4/4.
6. A fifth day is rejected.
7. Ordinary vacation can still be submitted after on-demand reaches 4/4 because the four-day rule is only a sublimit.
8. A Planned on-demand request cannot later transition to Requested while 4/4 is already reserved.
9. Balance page shows 4/4 and zero remaining.
10. My Requests visibly labels marked requests `Urlop na żądanie`.
