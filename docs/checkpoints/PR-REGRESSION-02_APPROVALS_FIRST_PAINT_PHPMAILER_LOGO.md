# PR-REGRESSION-02 — approvals first paint and PHPMailer inline logo

Date: 2026-08-17
Status: plan reviewed; patch reviewed; CI pending

## Reported production regressions

1. After approving a request, `/requests` briefly paints the legacy Jorani approvals table with Bootstrap status badges before the HAYNE approvals presentation appears.
2. Real Outlook notification mail still does not render the HAYNE image logo.
3. The PR79 `requests` null/TypeError regression is confirmed fixed and is not reopened by this work.

## Root causes

### Approvals first paint

The upstream `requests/index.php` renders its legacy heading, filters, Bootstrap labels and table immediately. HAYNE `approvals.js` is a client-side presentation enhancer and previously waited for `window.load`, then additionally waited for the DataTables wrapper. This leaves a visible interval in which the legacy view can paint.

### Mail logo

Jorani v1.0.4 configures the mail useragent as `PHPMailer`. Its `MY_Email::attach()` compatibility adapter accepts a fifth `$embedded_image` argument. With the default `false`, it calls PHPMailer `addStringAttachment()`, not `addStringEmbeddedImage()`.

The adapter also creates the CID internally when embedding. Calling `attachment_cid()` again after `attach()` would generate a different CID from the one already stored in the PHPMailer embedded image. The correct path is therefore:

- call `attach(..., '', true)`,
- read the existing CID through `get_attachment_cid()`,
- use that exact CID in the HTML `<img>`.

A real Outlook message delivered after PR79 was inspected through Microsoft Graph and had no attachment metadata, confirming that the isolated CI_Email test did not model the production PHPMailer path.

## Required process

Work follows the required order:

1. plan,
2. review plan,
3. patch,
4. review patch,
5. execution review through CI.

Both plan review and static patch review passed before PR creation.

## Runtime changes

### `/requests` first paint

- `hayne/patches/288-approvals-first-paint-guard.patch`
  - adds a synchronous, view-local pending marker before the legacy approvals markup,
  - marks only the host container of `requests/index.php`,
  - includes a 1500 ms fail-open.
- `hayne/overlay/assets/hayne/approvals-first-paint.css`
  - hides only direct legacy approvals payload while pending,
  - includes the DataTables wrapper so it cannot flash after DataTables initialization.
- `hayne/overlay/assets/hayne/approvals.js`
  - starts on `DOMContentLoaded` rather than `window.load`,
  - clears pending/`aria-busy` after final HAYNE page and rows are enhanced.
- `hayne/patches/150-approvals-assets.patch`
  - loads the small first-paint stylesheet in the document head.

### Mail logo

- `hayne/patches/286-ui-mail-branding-hotfix.patch`
  - uses Jorani `MY_Email` embedded-image mode,
  - gets the already-created CID through `get_attachment_cid()`,
  - preserves the existing text fallback and push isolation.

## Deterministic verification

The dedicated workflow must prove:

- patch 288 independently dry-runs against pristine Jorani v1.0.4,
- patches 150 and 286 still independently dry-run,
- final Docker image contains the prepaint CSS and marker before legacy heading,
- approvals enhancer starts at DOMContentLoaded and removes pending after enhancement,
- configured Jorani mail path is PHPMailer/MY_Email,
- final wrapper invokes embedded-image mode and reads the existing CID,
- actual Jorani `MY_Email` + PHPMailer `preSend()` creates `multipart/related`, inline disposition and a matching Content-ID,
- final PHPMailer MIME contains the same CID in HTML and the exact base64 bytes of runtime `logo.png`,
- no workflow/registry scope drift.

## Acceptance

- `/requests`, `/requests/requested` and `/requests/all` do not visibly paint the legacy Bootstrap approvals UI before HAYNE presentation,
- final approvals UI and actions remain unchanged,
- a newly delivered Outlook notification renders the actual HAYNE image logo,
- Microsoft Graph sees the delivered inline image attachment after production smoke,
- no regression in request approval, push, absence-policy routing or registry.

## Production

No production deployment is part of this branch. QNAP deployment remains a separate guarded offline rebuild/recreate after merge because QNAP GitHub access is currently rate-limited.
