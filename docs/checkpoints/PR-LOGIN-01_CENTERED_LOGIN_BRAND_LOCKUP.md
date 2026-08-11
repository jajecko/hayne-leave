# PR-LOGIN-01 — centered login and unified HAYNE Leave lockup

Status: verified on PR branch
Date: 2026-08-11

## Goal

Simplify the HAYNE Leave login screen to a single centered card and make the supplied HAYNE + LEAVE SVG the single visual source of truth for product branding across login/authenticated navigation/auth helper screens.

## User-facing changes

- remove the desktop split/left illustration panel from the login target,
- center one compact login card horizontally and vertically,
- use the supplied `logo.svg` lockup containing both HAYNE and LEAVE,
- remove separately rendered `Leave` / `LEAVE` labels below or beside the logo,
- remove the duplicated `HAYNE Leave` product heading under the login logo,
- keep one short helper sentence under the logo,
- tighten card, logo, input, button and vertical-spacing proportions,
- preserve a mobile-safe single-column layout with no horizontal scroll.

## Unified logo contract

`hayne/overlay/assets/hayne/logo.svg` is the only visual logo lockup. It contains:

- the original HAYNE vector paths,
- the LEAVE wordmark and side rules as vectors,
- no font dependency for the LEAVE wordmark.

The application must not reconstruct LEAVE with separate HTML text or CSS pseudo-elements.

## Login target

Desktop target:

- one centered card,
- card max width: 440px,
- logo width: approximately 204px,
- input height: 50px,
- primary action min-height: 50px,
- no left branding/illustration column,
- no top-left duplicate logo,
- no secondary `HAYNE Leave` heading.

Mobile target:

- centered card with safe 14px viewport gutter,
- compact card padding,
- logo scales down intrinsically,
- no horizontal overflow.

## Files in scope

- `hayne/overlay/assets/hayne/logo.svg`
- `hayne/overlay/assets/hayne/login.css`
- `hayne/overlay/assets/hayne/login.js`
- `hayne/patches/020-menu-branding.patch`
- `hayne/patches/030-login-branding.patch`
- `hayne/patches/040-oauth-login-branding.patch`
- `hayne/patches/050-session-failure-branding.patch`
- `hayne/patches/060-oauth-authorize-branding.patch`
- `.github/workflows/verify-pr-login-01.yml`
- this checkpoint

## Guardrails

No changes to:

- login endpoint or request method,
- session handling,
- password validation,
- CSRF behavior,
- authentication/authorization rules,
- LDAP/SAML behavior,
- database schema/data,
- leave workflow,
- manager approval workflow.

This is presentation/branding only.

## Acceptance

Automated:

- every HAYNE patch dry-runs independently against pristine Jorani v1.0.4,
- normal Docker build succeeds,
- built menu/login/failure/OAuth views reference `assets/hayne/logo.svg`,
- built views do not contain `hayne-navbar-product` or `hayne-login-product-name`,
- `login.js` does not reconstruct a LEAVE submark,
- rendered login reaches `data-hayne-login="target-v2"`,
- rendered login contains the helper sentence,
- desktop and 390px mobile screenshots are captured.

Visual review:

- exactly one visible logo lockup on the login screen,
- login card is visually centered,
- no left-side panel/illustration,
- no separate LEAVE text under the logo,
- no duplicate HAYNE Leave heading,
- inputs/button have balanced proportions,
- authenticated sidebar uses the same supplied HAYNE + LEAVE lockup.

## Verification evidence

PR: #22

Initial verified head: `116057492ca536b01ecc24291f25f78310065ffa`

GitHub Actions:

- `verify` run #122 / `31481786447` — PASS,
- `verify-pr-login-01` run #1 / `31481786508` — PASS.

The dedicated run passed:

- unified SVG source guards,
- Compose validation,
- independent dry-run of all HAYNE patches against Jorani v1.0.4,
- full HAYNE image build,
- built-image branding guards,
- rendered login DOM assertions,
- desktop 1440x1000 screenshot capture,
- mobile 390x844 screenshot capture.

Visual review of `pr-login-01-evidence` confirmed:

- desktop: one compact centered card, no left panel, no duplicated product heading, integrated HAYNE + LEAVE logo, balanced 50px inputs/action,
- mobile: centered single card with safe viewport gutters and no visible overflow,
- authenticated home screenshot from the standard `verify` artifact: sidebar renders the same integrated HAYNE + LEAVE SVG with no separate Leave line under the logo.

A final CI rerun is required after this checkpoint-only update before merge.

## Deployment note

The current temporary QNAP production deployment is rebuilt manually from the repository overlay/patch layer. After merge, production should be rebuilt from the merged main commit; do not patch the running container by hand.
