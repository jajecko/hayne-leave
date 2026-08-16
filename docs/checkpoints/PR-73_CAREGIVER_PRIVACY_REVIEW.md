# PR #73 — PRIVACY-01 caregiver privacy + manager review UX

## Problem

The caregiver leave flow stores statutory metadata in `hayne_caregiver_request_meta`: person name, relation, household address when applicable, and care/support reason. Those fields are necessary for the formal leave request but are more detailed than a line manager needs to organize absence coverage.

Two manager-facing paths also exposed free-text content that could contain family/health details:

- dedicated `/requests/review/{id}` rendered the generic leave `cause`;
- manager notification email rendered `Uzasadnienie` / cancellation comment without distinguishing caregiver leave.

The generic Jorani leave-detail page also loaded the caregiver metadata partial for every authorized viewer, including the employee's manager/delegate.

Separately, the manager review `Akceptuj wniosek` CTA remained explicitly black in the existing stylesheet.

## Access decision

Structured caregiver metadata is server-side gated:

- employee viewing their own request: allowed;
- HR: allowed;
- admin: allowed;
- line manager: not allowed;
- manager delegate: not allowed.

This is not implemented with CSS hiding. Manager/delegate render data does not contain `hayneCaregiverDetails`.

## Manager review

For caregiver leave, manager/delegate sees only operational request data:

- employee;
- leave type;
- term;
- duration;
- neutral notice: `Dane osoby wymagającej opieki lub wsparcia są dostępne wyłącznie dla kadr.`

The generic `cause` is not rendered for that manager/delegate review.

HR/admin using the same HAYNE review route receives the structured caregiver detail block.

The accept CTA gets a green override (`#176b42`, darker hover/focus) loaded after the existing review stylesheet. Reject remains the existing red treatment. The review assets are cache-busted.

## Generic leave detail

A late controller patch checks role before rendering `leaves/view.php`:

- owner/HR/admin keep caregiver metadata;
- manager/delegate receives `hayneCaregiverDetails = NULL` and a neutral formal-data notice in place of the free-text cause.

## Manager email privacy

`Requests` now adds a boolean `HayneSensitiveCaregiver` template context using the configured caregiver `leave_type_id`, never the translated type name.

For caregiver requests/cancellation requests sent to the manager:

- free-text reason/comment is replaced by the same neutral formal-data notice;
- no structured caregiver metadata is added to the mail.

Employee outcome emails are not changed.

## Files

- `hayne/overlay/legacy/application/controllers/Hayneapprovals.php`
- `hayne/overlay/legacy/application/views/requests/review.php`
- `hayne/overlay/assets/hayne/approval-review-privacy.css`
- `hayne/overlay/legacy/application/views/emails/pl/request.php`
- `hayne/overlay/legacy/application/views/emails/pl/cancel.php`
- `hayne/patches/282-caregiver-detail-privacy.patch`
- `hayne/patches/283-caregiver-manager-mail-privacy.patch`
- `.github/workflows/verify-pr-caregiver-privacy-review.yml`

## Explicit non-scope

- no change to caregiver entitlement (5 days);
- no change to required caregiver request fields;
- no change to accept/reject state machine yet;
- no HR-only workflow redesign yet;
- no alert-system redesign;
- no email logo change;
- no official-summons credit fix (separate PR #72).

## Required smoke after deployment

1. Open a caregiver request as its line manager through the email CTA.
2. Confirm manager sees employee/type/term/duration and the neutral formal-data notice.
3. Confirm page source does not contain test values for person name, relation, household address or care reason.
4. Confirm generic `leaves/requests/{id}` view behaves the same for manager/delegate.
5. Open the same request as HR/admin and confirm full caregiver details are visible.
6. Open the request as the employee and confirm their own caregiver details remain visible.
7. Confirm manager request email for caregiver does not contain the free-text reason; cancellation email does not contain the free-text cancellation comment.
8. Confirm a non-caregiver manager email/review still shows the normal reason/comment.
9. Confirm `Akceptuj wniosek` is green, hover/focus remain readable, and reject stays red.
10. Accept and reject controlled non-sensitive requests to confirm native mutation endpoints remain unchanged.
