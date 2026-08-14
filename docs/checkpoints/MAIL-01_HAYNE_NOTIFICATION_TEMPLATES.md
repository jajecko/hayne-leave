# MAIL-01 — HAYNE Leave workflow notifications

## Goal
Replace the legacy Jorani Polish leave-workflow emails with a compact HAYNE Leave notification system while preserving the existing leave workflow and recipient rules.

## Transport precondition verified on 2026-08-14
The deployment SMTP path was verified before this change:
- QNAP/application container can reach `hayne-pl.mail.protection.outlook.com:25`;
- STARTTLS negotiation succeeds;
- Exchange Online connector recognizes public source IP `217.168.142.122`;
- sender `HAYNE Leave <urlopy@hayne.pl>` is accepted;
- raw SMTP smoke reached `250 2.6.0 Queued mail for delivery` and was delivered;
- Jorani `sendMailByWrapper()` / PHPMailer smoke was invoked with RC 0 and the message was delivered.

Deployment note: the QNAP Compose file uses `app.env` for the application container. Root `.env` is not the runtime source for SMTP variables in that deployment.

## Scope
### Templates
Polish workflow templates are supplied as overlay files for:
- new leave request → manager/delegates;
- accepted leave request → employee;
- rejected leave request → employee;
- cancellation request → manager/delegates;
- cancellation accepted → employee;
- cancellation rejected → employee;
- direct employee cancellation → manager/delegates.

### Content policy
Notification emails contain only operational data needed to identify the request:
- employee name where the recipient is the manager;
- leave type;
- date range;
- number of days;
- workflow status;
- one authenticated CTA to the request details.

The templates intentionally do not expose:
- leave reason/cause;
- leave balance;
- comments;
- one-click accept/reject actions.

Approval and rejection remain actions performed inside authenticated HAYNE Leave.

## Implementation
- `hayne_mail_helper.php` centralizes email-safe HAYNE styling and HTML escaping.
- manager CTAs use the existing request detail route `leaves/requests/{id}`.
- employee CTAs use the existing own-request detail route `leaves/leaves/{id}`.
- `274-mail-template-context.patch` adds only `Duration`, `BaseUrl`, and `LeaveId` to the existing employee decision email parser context.
- existing `272-web-push-mail-bridge.patch` now also records `HAYNE Mail SENT` / `HAYNE Mail FAILED` with controller source and a truncated SHA-256 recipient key instead of a clear-text address.
- SMTP send exceptions are caught so a mail transport failure does not block the leave workflow. Existing Web Push remains best-effort and is still invoked after the email attempt.
- `.env.example` documents the verified Exchange Online relay topology without credentials.

## CI correction
The first PR run failed because a separate `280-mail-delivery-resilience.patch` expected the output of patch 272. The repository patch checker validates every patch independently against a clean Jorani v1.0.4 tree, so dependent patches are invalid even if Docker would apply them sequentially. The resilience change was therefore folded into patch 272 and patch 280 removed.

## Guardrails
No changes to:
- leave status transitions;
- authorization rules;
- manager/delegate recipient resolution;
- database schema or stored leave data;
- Active Directory sync/auth;
- Web Push subscription data or payload policy;
- overtime workflow;
- production secrets.

## Verification required before merge/deployment
1. `git diff --check`.
2. PHP syntax check for the new helper and all seven templates.
3. Docker build to prove overlay copy and patch application against Jorani v1.0.4.
4. After manual QNAP deployment, create a controlled leave request and confirm manager email rendering/CTA.
5. Accept and reject controlled requests and confirm employee emails.
6. Exercise cancellation request + accept/reject paths.
7. Confirm logs show `HAYNE Mail SENT` without clear-text recipient addresses.
