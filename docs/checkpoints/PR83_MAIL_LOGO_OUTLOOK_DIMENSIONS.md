# PR83 — MAIL LOGO OUTLOOK DIMENSIONS

## Problem

Po PR82 inline logo PNG dociera do wiadomości i jest referencjonowane przez CID, ale Outlook renderuje tylko fragment grafiki.

## Potwierdzona charakterystyka assetu

- `hayne/overlay/assets/hayne/logo.png`
- rozmiar: `1669x234`
- PNG zawiera metadane 300 DPI
- dotychczasowy tag mailowy miał `width="180"` oraz CSS `height:auto`, bez jawnego atrybutu HTML `height`

## Zakres

Zmiana tylko w `hayne/patches/286-ui-mail-branding-hotfix.patch`:

- zachowanie istniejącego CID/PHPMailer,
- zachowanie istniejącego PNG,
- odczyt geometrii przez `getimagesize()`,
- target width `180`,
- proporcjonalny target height `25`,
- jawne `width="180" height="25"`,
- jawne CSS `width:180px;height:25px;max-width:180px`,
- usunięcie `height:auto` dla mailowego logo.

## Guardrails

Bez zmian:
- SMTP,
- routing mail/push,
- treść maila poza `<img>` logo,
- DB/schema/data,
- registry,
- workflow_mode,
- saldo/entitlement,
- self-approval security,
- PWA/AD.

## Test regresyjny

`.github/workflows/verify-pr-mail-logo-outlook-dimensions.yml` sprawdza:

1. patch 286 independent dry-run na Jorani v1.0.4,
2. finalny build i PHP lint,
3. source PNG `1669x234`,
4. wyliczenie target `180x25`,
5. brak `height:auto`,
6. finalny PHPMailer MIME z `multipart/related`, CID, inline disposition, realnymi bajtami PNG oraz jawnymi wymiarami `180x25`.

Proces: plan -> review planu -> patch -> review patcha.
