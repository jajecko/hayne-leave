# UI-MAIL-HOTFIX — review CTA, alerts and inline mail logo

Date: 2026-08-17
Status: implementation / review

## Scope

Isolated presentation and mail-branding hotfix only:

1. Keep the manager-review accept CTA green with white text despite the global `#wrap a` rule.
2. Replace legacy broad yellow alert presentation with a neutral white surface, semantic border accents, readable text and a close control positioned inside the alert border.
3. Restore the real HAYNE logo in notification e-mails as a CID inline attachment instead of relying on a remote image URL.

No leave-policy, registry, workflow, balance, entitlement, database, AD, PWA, push-routing or production data changes are in scope.

## Root causes

### Review CTA

`assets/hayne/app.css` applies a global `#wrap a { color: #111; }` rule. The green accept CTA was styled without an ID selector, so the global rule could win the cascade and make its label dark.

The hotfix raises only the accept CTA selector specificity in the late-loaded review privacy stylesheet by anchoring it below `#wrap`.

### Alerts

The HAYNE layer previously changed only the alert border radius. Legacy Bootstrap warning backgrounds and close-button geometry therefore remained visible.

The hotfix gives alerts a white background, dark text, semantic border colors and right-side padding. The close control is absolutely positioned at `right: 12px` and vertically centered inside the alert.

### Mail logo

The existing HAYNE helper intentionally used a text-only wordmark after the previous remote image header proved unreliable in mail clients.

The hotfix changes the helper to emit the internal `<!--HAYNE_MAIL_LOGO-->` token. The existing `sendMailByWrapper()` resolves that token immediately before the e-mail body is assigned:

- fallback is the safe text HAYNE Leave wordmark,
- when `assets/hayne/logo.png` exists, CodeIgniter attaches it with disposition `inline`,
- `CI_Email::attachment_cid()` supplies the Content-ID,
- the token is replaced with an `<img src="cid:...">`,
- exceptions are logged and fall back to text,
- the original `$message` is preserved for the existing push bridge.

This avoids external URL, DNS, TLS, mail-proxy and remote-image-loading dependencies.

## Runtime files affected by patch 286

- `assets/hayne/app.css`
- `assets/hayne/approval-review-privacy.css`
- `legacy/application/helpers/hayne_mail_helper.php`
- `legacy/application/helpers/tools_helper.php`

Tracked implementation file:

- `hayne/patches/286-ui-mail-branding-hotfix.patch`

Verification:

- `.github/workflows/verify-pr-ui-mail-branding-hotfix.yml`

## Verification contract

The dedicated workflow must prove:

- patch 286 applies during the normal Docker build after the complete existing patch stack,
- final PHP helpers lint,
- final alert CSS contains the internal close-button geometry and neutral background,
- final accept CTA selectors include `#wrap` and preserve white text,
- final mail helper emits the logo token,
- final mail wrapper uses native `attach(..., 'inline', ...)` and `attachment_cid()`,
- upstream CodeIgniter in the built image exposes both attachment methods,
- `assets/hayne/logo.png` exists in the built image,
- SHA-256 of the source logo equals SHA-256 of the logo in the built image,
- patch 286 does not touch the absence-policy registry/workflow model or SQL.

## Production

No production deployment is part of the implementation PR. The QNAP deployment is a separate guarded step after merge. The earlier attempted deployment on 2026-08-17 stopped at the source-contract gate before source replacement, build or container recreation because the hotfix was not yet present on `main`.
