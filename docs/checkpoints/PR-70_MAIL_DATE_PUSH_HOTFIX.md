# PR-70 — Mail logo, current-year create range and decision push

Status: implementation ready for CI
Date: 2026-08-16

## User-visible problems
1. The HAYNE logo in workflow e-mail is clipped/broken in the received message.
2. The new-request date picker does not expose the intended full January–December range and still allows navigating to prior years.
3. Employee Web Push after manager decisions is generic (`Status Twojego wniosku został zaktualizowany.`) instead of stating the actual outcome.
4. Production manager review still shows black text on the black accept CTA, although this is already fixed on `main` by PR #67 and therefore is treated as a deployment/runtime drift, not a new CSS change.

## Changes

### E-mail header
`hayne_mail_helper.php` no longer relies on a remotely loaded raster logo in the e-mail header. The header is rendered as an email-client-safe table/text HAYNE LEAVE wordmark using inline styles. This avoids clipping, external-image blocking and inconsistent raster scaling in Outlook-class clients while preserving the black/white HAYNE visual identity.

### New request calendar boundary
A new independent patch applies only to the employee new-request surface:
- browser datepickers expose the complete current calendar year from 1 January through 31 December;
- the year selector is restricted to the current year;
- the boundary is dynamic (`new Date().getFullYear()` / `date('Y')`), not hard-coded to 2026;
- server-side validation rejects POSTs outside the current calendar year, so manually crafted requests cannot bypass the UI;
- existing requests, history and historical views are untouched.

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
- manager/delegate routing;
- AD;
- SMTP/Exchange transport configuration;
- Web Push subscription schema or VAPID secrets;
- database schema;
- historical request visibility;
- manager review CSS already fixed by PR #67.

## CI
Dedicated workflow `verify-pr-mail-date-push-hotfix.yml`:
- builds the full HAYNE Leave image, proving patch application against Jorani v1.0.4;
- PHP-lints final mail/push helpers and `Leaves.php`;
- verifies the final mail-to-push bridge receives subject/message context;
- verifies all four explicit employee push outcomes;
- verifies e-mail header no longer references `assets/hayne/logo.png`;
- verifies January 1 / December 31 current-year picker limits and server-side current-year guard.

## Required QNAP smoke after merge/deployment
1. Deploy current `main` as one complete rebuild/recreate, not individual runtime files.
2. Confirm manager review accept CTA has white text on black background (PR #67 is present in current `main`).
3. Create a new request and confirm the picker is limited to the current year and includes December 31.
4. Confirm the new-request manager e-mail renders the complete HAYNE LEAVE wordmark.
5. Accept a controlled request: employee receives the acceptance e-mail and explicit acceptance push.
6. Reject a controlled request: employee receives the rejection e-mail and explicit rejection push.
7. Request cancellation and test both manager outcomes: employee receives matching e-mail + explicit push for cancellation accepted/rejected.
8. Check application logs for `HAYNE Mail SENT`/`FAILED` and push errors; confirm the relevant subscription updates `last_success_at` and keeps `failure_count=0` after successful delivery.
