# PR-MAIL-02 — Manager approval UX, branded mail and duplicate-submit protection

Status: implementation ready for CI
Date: 2026-08-14

## User feedback addressed
Production smoke after MAIL-01 revealed four concrete issues:

1. Manager email was visually generic and did not use the real HAYNE Leave application logo.
2. Manager email omitted the employee's request justification, leaving the approver without decision context.
3. `Zobacz wniosek` opened the generic legacy leave detail surface, where edit/reminder/back actions dominated and there were no obvious approval controls.
4. Rapidly clicking `Wyślij wniosek` more than once created separate leave records for every submitted POST.

## Decisions

### Email
- Use the existing application raster `assets/hayne/logo.png` in the email header for Outlook-friendly rendering.
- Keep a text `HAYNE Leave` fallback if the asset URL cannot be derived.
- Rework the email chrome toward the application's white/black/neutral visual system.
- Manager-facing new-request mail includes `Reason` as `Uzasadnienie`.
- Cancellation-request mail includes the latest request comment when present.
- The manager CTA opens `/requests/review/{id}` instead of the generic `/leaves/requests/{id}` view.
- Cancellation-review mail uses the same manager decision route.

### Manager decision surface
- Add an explicit `requests/review/{id}` route pointing to a thin HAYNE read-only controller `Hayneapprovals`.
- Do not modify Jorani `Requests.php` to render the review screen.
- Reuse the same authorization boundary as existing request management: manager, HR, or valid delegate only.
- Keep existing `requests/accept`, `requests/reject`, `requests/cancellation/accept`, and `requests/cancellation/reject` endpoints as the only status mutation paths.
- New view prioritizes employee, type, date range, duration, justification and current status.
- Decision card is the primary action area with large `Akceptuj` / `Odrzuć` controls.
- Rejection uses a CI form and respects `mandatory_comment_on_reject`.
- Legacy edit and reminder controls are not exposed on this manager decision surface.
- History/comments remain available below the request data as secondary context.
- Layout becomes one-column on smaller screens.

### Duplicate submit protection
Two layers are used:

1. Browser guard immediately locks the form after the first submit event without disabling the submitter value (`status` remains part of POST data).
2. Server-side one-shot token stored in the authenticated session is consumed before `setLeaves()` executes. A repeated POST from the same rendered form is rejected before any DB write or notification.

Up to eight unexpired tokens are kept per session so opening the create form in multiple tabs does not invalidate another tab. Tokens expire after two hours.

Jorani v1.0.4 uses CodeIgniter's database session driver. On MySQL the driver acquires `GET_LOCK()` for the session ID before reading session state and releases it when the request closes. Concurrent POSTs from the same authenticated session are therefore serialized: the second request reads the already-consumed one-shot token and cannot create another leave record.

## Guardrails
No changes to:
- leave balance calculation;
- FIFO entitlement logic;
- status definitions;
- manager/delegate assignment;
- AD sync/auth;
- Exchange Online transport configuration;
- Web Push transport;
- database schema.

## Verification
- all new patches must apply independently to clean Jorani v1.0.4;
- Docker build must apply the full patch sequence;
- PHP lint for `Hayneapprovals.php`, `requests/review.php` and `hayne_mail_helper.php`;
- verify mail HTML contains `logo.png`, `Uzasadnienie`, and manager CTA `/requests/review/{id}`;
- E2E: double click / rapid repeated click on `Wyślij wniosek` creates exactly one DB record and one notification;
- E2E: manager opens mail CTA, sees the dedicated decision view, accepts and rejects controlled requests successfully;
- E2E: cancellation request + accept/reject uses the same review surface.
