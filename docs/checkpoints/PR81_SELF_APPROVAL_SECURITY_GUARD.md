# PR81 — Self-approval security guard

Date: 2026-08-17

## Problem

A leave owner could reach the reviewer flow while logged in as the same employee and approve their own request when their account also satisfied an approver role/relationship check. The workflow authorization predicate did not compare the authenticated actor with `leaves.employee`.

## Security invariant

A request owner must never decide their own leave request through reviewer actions, regardless of manager, delegate, HR or admin privileges and regardless of registry fallback behavior.

## Scope

- `hayne/overlay/legacy/application/models/Hayne_leave_workflow_model.php`
- `hayne/overlay/legacy/application/controllers/Hayneapprovals.php`
- `hayne/patches/285-registry-workflow-routing.patch`
- `.github/workflows/verify-pr-absence-policy-workflow-mode.yml`

No database/schema/data, leave balances, entitlement rules, registry catalog, mail, push, PWA or AD changes.

## Implementation

`canActorDecide()` now receives both the authenticated actor user id and request employee user id. It returns `FALSE` before evaluating workflow mode or roles when both ids are valid and equal.

All four reviewer mutations in `Requests` pass the actor and owner ids:

- accept request,
- reject request,
- accept cancellation,
- reject cancellation.

The read-only HAYNE review surface passes the same ids, so a self-owner cannot open the reviewer action page through a direct mail URL.

The employee-owned leave cancellation/withdrawal workflow in `Leaves` is outside this reviewer guard and remains unchanged.

## Deterministic verification

The workflow-mode CI contract now verifies:

- HR manager/delegate remains denied for HR-routed types unless HR/admin,
- non-owner HR/admin remains allowed for HR workflow,
- non-owner manager/delegate/HR behavior for APPROVAL is unchanged,
- admin-only behavior for APPROVAL is unchanged,
- NONE remains non-decidable,
- missing registry policy preserves native fallback for non-owners,
- self-owner is denied as manager, delegate, HR/admin and in registry fallback,
- final patch stack contains actor/employee wiring in all four `Requests` decision sites and in `Hayneapprovals`.

## Review

Plan review: PASS.

Patch review before PR: PASS. Diff contains only the authorization model, its five call sites/wiring, deterministic tests and this checkpoint. No unrelated runtime scope was introduced.
