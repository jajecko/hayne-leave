# PR-70 — Mail branding and decision push hotfix

Status: implementation ready for CI
Date: 2026-08-16

## User-visible problems
1. The HAYNE logo in workflow e-mail is clipped/broken in the received message.
2. Employee Web Push after manager decisions is generic (`Status Twojego wniosku został zaktualizowany.`) instead of stating the actual outcome.
3. Production manager review still shows black text on the black accept CTA, although this is already fixed on `main` by PR #67 and therefore is treated as a deployment/runtime drift, not a new CSS change.

## Changes

### E-mail header
`hayne_mail_helper.php` no longer relies on a remotely loaded raster logo in the e-mail header. The header is rendered as an email-client-safe table/text HAYNE LEAVE wordmark using inline styles. This avoids clipping, external-image blocking and inconsistent raster scaling in Outlook-class clients while preserving the black/white HAYNE visual identity.

### Leave request dates
No custom calendar-year restriction is introduced in this PR. New leave requests keep Jorani's native date-range behavior, so a request can also target the next calendar year when needed (for example, a request submitted in December for January). There is no HAYNE server-side guard rejecting dates solely because they fall outside the current calendar year.

### Decision Web Push
The mail-to-push bridge now passes the rendered workflow mail context into the push helper. For employee decision notifications the helper maps the existing trusted HAYNE mail copy to an explicit push body:
- accepted -> `Twój wniosek urlopowy został zaakceptowany.`
- rejected -> `Twój wniosek urlopowy został odrzucony.`
- cancellation accepted -> `Anulowanie Twojego wniosku zostało zaakceptowane.`
- cancellation rejected -> `Prośba o anulowanie Twojego wniosku została odrzucona.`

Unknown/legacy employee mail content retains the safe generic fallback. Manager-side push for a new request remains unchanged.

## Guardrails
No changes to:
- leave balance/FIFO calculations;
- workflow status transitions;
- leave date eligibility or calendar-year business rules;
- manager/delegate routing;
- AD;
- SMTP/Exchange transport configuration;
- Web Push subscription schema or VAPID secrets;
- database schema;
- historical request visibility;
- manager review CSS already fixed by PR #67.

## CI
Dedicated workflow `verify-pr-mail-date-push-hotfix.yml` (display name `verify-pr-mail-push-hotfix`):
- builds the full HAYNE Leave image against Jorani v1.0.4;
- PHP-lints final mail/push helpers;
- verifies the final mail-to-push bridge receives subject/message context;
- verifies all four explicit employee push outcomes;
- verifies e-mail header no longer references `assets/hayne/logo.png`.

## Required QNAP smoke after merge/deployment
1. Deploy current `main` as one complete rebuild/recreate, not individual runtime files.
2. Confirm manager review accept CTA has white text on black background (PR #67 is present in current `main`).
3. Confirm the new-request calendar is not restricted by HAYNE to the current year; in particular, next-year dates remain available when the native Jorani picker permits them.
4. Confirm the new-request manager e-mail renders the complete HAYNE LEAVE wordmark.
5. Accept a controlled request: employee receives the acceptance e-mail and explicit acceptance push.
6. Reject a controlled request: employee receives the rejection e-mail and explicit rejection push.
7. Request cancellation and test both manager outcomes: employee receives matching e-mail + explicit push for cancellation accepted/rejected.
8. Check application logs for `HAYNE Mail SENT`/`FAILED` and push errors; confirm the relevant subscription updates `last_success_at` and keeps `failure_count=0` after successful delivery.
