# ABSENCE-POLICY-04 — registry workflow mode routing

Status: implementation checkpoint
Date: 2026-08-17

## Goal

Make `hayne_leave_type_registry.workflow_mode` control who reviews a pending leave request without introducing a new leave status or rewriting Jorani's workflow state machine.

`LMS_REQUESTED` remains the single pending state. The registry decides whether the pending request belongs to the employee's normal manager route or to the HR verification route.

## MVP workflow modes

- `APPROVAL` — preserve native Jorani decision authority: line manager, valid delegate or HR.
- `HR` — only HR/admin may review and accept/reject; line manager and manager delegate are not approvers.
- `NONE` — no approval path. Current `NONE` types are already inactive for new requests through ABSENCE-POLICY-02.
- missing/disabled registry row — preserve upstream Jorani authorization as a compatibility fallback.

Current registry distribution:

- `APPROVAL`: 1, 3, 8, 12, 13, 20;
- `HR`: 4, 5, 6, 7, 9, 10, 11, 14, 15, 16;
- `NONE`: 2, 17, 18, 19.

## Runtime behavior

### New request / notification

All existing request statuses remain unchanged.

For `workflow_mode=APPROVAL`, request/cancellation notifications keep the native manager recipient and manager-delegate CC behavior.

For `workflow_mode=HR`:

- the employee's `users.manager` is not used as notification recipient;
- the first active Jorani user carrying role bit `8` (`HR admin`) and a non-empty e-mail is the primary recipient;
- remaining active HR recipients are copied;
- manager delegates are not copied;
- the same routing applies to new requests, cancellation requests and cancellation notifications because all three use the same HAYNE recipient adapter.

No recipient is inferred from a display name or a hard-coded employee ID.

### Missing HR recipient safety

A pending HR-routed request must not be created without a real notification recipient.

`Hayne_leave_workflow_model::hasRequiredNotificationRecipient()` therefore requires at least one active Jorani user with role bit `8` and a non-empty e-mail for `workflow_mode=HR`. Standard workflows keep upstream behavior.

The guard is enforced before transitions into an HR pending state in:

- new request creation with `LMS_REQUESTED`;
- editing a request into `LMS_REQUESTED` or `LMS_CANCELLATION`;
- promoting a planned request to `LMS_REQUESTED`;
- requesting cancellation of an accepted request (`LMS_CANCELLATION`).

If no active HR recipient with e-mail exists, the pending transition is not persisted and the user receives:

`Nie można przekazać wniosku do HR: brak aktywnego konta HR z adresem e-mail. Skontaktuj się z administratorem.`

There is no fallback to the employee's line manager for an HR-routed request.

### Pending queue

Native Jorani `Requests::index()` only queries requests through `users.manager`. ABSENCE-POLICY-04 therefore applies a post-query policy adapter:

- a normal manager queue removes `HR` workflow requests even when the employee reports to that manager;
- HR/admin receives the global set of `HR` workflow requests merged into any ordinary requests they can already review;
- duplicate rows are collapsed by leave ID;
- pending mode still means `Requested` or `Cancellation`;
- `show all` keeps the same native status scope and additionally includes all HR-workflow statuses.

A direct `Wnioski do weryfikacji` link is added to the existing HR/admin administration menu so an HR user does not need to be a line manager to reach the queue.

### Authorization

The same adapter is enforced in:

- `Requests::accept()`;
- `Requests::reject()`;
- cancellation accept;
- cancellation reject;
- HAYNE `/requests/review/{id}` review surface.

A manager/delegate cannot bypass the HR route by guessing an action URL.

## Safety / compatibility

- no new DB table;
- no new status;
- no changes to `entitleddays`;
- no changes to `types.deduct_days_off`;
- no changes to ABSENCE-POLICY-03 balance behavior;
- no workflow inference from translated names;
- no production type IDs hard-coded in routing code;
- no change to FIFO/statutory-pool calculations;
- no Home Office module;
- no production QNAP deployment in this PR.

The adapter intentionally keeps upstream behavior when a registry row is missing. Unknown registry workflow modes are logged and treated as missing rather than guessed.

## Acceptance criteria

1. The 20-row registry resolves to 6 `APPROVAL`, 10 `HR`, and 4 `NONE` policies.
2. Line manager and delegate cannot decide an `HR` request.
3. HR/admin can decide an `HR` request.
4. Normal `APPROVAL` authorization remains compatible with Jorani.
5. HR-mode notifications do not use the employee's manager or manager delegates.
6. An HR pending transition is blocked when no active HR recipient with e-mail exists; there is no manager fallback.
7. Normal manager request lists exclude HR-mode requests.
8. HR/admin request list includes the global HR-mode queue.
9. HAYNE review route and all four mutation endpoints enforce the same workflow adapter.
10. Full patch stack applies to Jorani v1.0.4, final PHP lints, and the Docker image builds.
