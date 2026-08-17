# PR82 — Mail logo FCPATH fix

Date: 2026-08-17

## Problem

After the PHPMailer embedded-image fix, delivered HAYNE Leave notifications still rendered the textual HAYNE/LEAVE fallback instead of the PNG logo.

## Confirmed root cause

Jorani v1.0.4 defines `FCPATH` as the directory containing `legacy/index.php`, therefore the runtime value is `/var/www/html/legacy/` in the HAYNE Leave image.

The wrapper used:

```php
$mailLogoPath = FCPATH . 'assets/hayne/logo.png';
```

which resolved to the nonexistent `/var/www/html/legacy/assets/hayne/logo.png`.

The tracked/runtime logo is actually `/var/www/html/assets/hayne/logo.png`. `is_file($mailLogoPath)` therefore returned false and the existing safe textual fallback was used. This exactly matches the delivered mail symptom.

PR80's MIME test used `/var/www/html/assets/hayne/logo.png` directly, so it verified PHPMailer's embedded-image mechanics but bypassed the faulty wrapper path resolution.

## Fix

The wrapper now resolves the asset from the parent of Jorani's legacy front-controller directory:

```php
$mailLogoPath = dirname(FCPATH) . DIRECTORY_SEPARATOR . 'assets/hayne/logo.png';
```

No mail template, SMTP, PHPMailer, push or notification routing behavior is otherwise changed.

## Deterministic verification

Both mail regression workflows now:

- assert the final wrapper uses `dirname(FCPATH)` and does not contain the old `FCPATH . 'assets/...'` path,
- define the actual runtime `FCPATH` as `/var/www/html/legacy/`,
- require the resolved path to equal `/var/www/html/assets/hayne/logo.png`,
- require the file to exist,
- build the inline attachment from that resolved path,
- verify CID/MIME behavior and exact PNG bytes.

## Guardrails

No database/schema/data, leave workflow, registry, balances, AD, PWA or SMTP configuration changes.

Plan review: PASS.

Patch review before PR: PASS. Runtime change is one root-cause line plus regression-test hardening and this checkpoint.
