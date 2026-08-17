# PR-REGRESSION-01 — review action, login first paint and mail logo

Date: 2026-08-17
Status: implementation reviewed; CI pending

## Reported production regressions

1. After accepting a leave from the review flow opened from e-mail, `/requests/requested` can fail with:
   - `Undefined array key "requests"` in `Requests.php`,
   - followed by `TypeError` because `filterAndMergeApproverRequests()` receives `null` instead of `array`.
2. The legacy Jorani login layout is visible briefly before the HAYNE login target appears.
3. Real notification e-mails show a broken/missing HAYNE logo even though the CID code is present.

## Root causes

### Requests list

Patch 285 calls the strictly typed `filterAndMergeApproverRequests(array $managerRequests, ...)` with `$data['requests']` directly. Production evidence shows that the key can be absent on the post-review redirect path. The upstream manager-query methods themselves declare `: array`; the regression fix therefore does not weaken workflow-model typing or alter query semantics. It initializes `$data['requests']` to an empty array before the existing history/non-history query branch.

### Login first paint

The final login layout is scoped to `body.hayne-login-target`, but `login.js` previously added that class only during client enhancement after page parsing. The browser can therefore paint the server-rendered legacy layout first.

Patch 287 sets the `hayne-login-target` body class server-side in `templates/header.php` only when CodeIgniter routes to `session/login`. The existing JS remains idempotent and continues to provide icons, placeholders and password-toggle behavior.

### Mail logo

Patch 286 previously called:

`attach($mailLogoPath, 'inline', 'hayne-logo.png', 'image/png')`

In the pinned CodeIgniter `CI_Email::attach()` implementation, supplying a non-empty MIME argument makes the first argument be treated as already-buffered file content. Because the first argument was actually a filesystem path, the MIME attachment contained the text of the path rather than the PNG bytes. The CID was valid, but the referenced image payload was invalid.

The fix calls:

`attach($mailLogoPath, 'inline', 'hayne-logo.png')`

This causes CodeIgniter to open the file, read the actual PNG bytes, derive the MIME type, and then `attachment_cid()` marks the attachment multipart/related.

## Runtime changes

- `hayne/patches/286-ui-mail-branding-hotfix.patch`
  - remove the explicit fourth MIME argument from the inline-logo `attach()` call.
- `hayne/patches/287-review-login-regression-hotfix.patch`
  - initialize `$data['requests'] = []` before the existing manager request query branch,
  - render `class="hayne-login-target"` on `<body>` server-side only for `session/login`.

No database, registry, entitlement, leave type, HR routing, AD, PWA or production configuration changes are in scope.

## Review sequence

The work followed the required sequence:

1. plan,
2. plan review,
3. patch,
4. patch review.

The first patch review rejected redundant `(array)` casts because both upstream manager-query methods already declare `: array`. Those casts were removed. The review also found and corrected a shell-expansion bug in the new regression workflow before PR creation.

## Deterministic verification

### Existing UI/mail workflow

`.github/workflows/verify-pr-ui-mail-branding-hotfix.yml` is strengthened to:

- require the corrected three-argument `attach()` call,
- reject the old four-argument form,
- instantiate native `CI_Email`,
- attach the real runtime `logo.png`,
- obtain a CID,
- inspect the private attachment payload,
- base64-decode it,
- compare its SHA-256 byte-for-byte with the actual PNG,
- require MIME `image/png`, multipart `related`, and matching CID.

### New regression workflow

`.github/workflows/verify-pr-review-login-regressions.yml` must prove:

- patch 287 independently dry-runs against pristine Jorani v1.0.4,
- the complete overlay + patch stack applies,
- final `Requests.php`, `header.php` and `tools_helper.php` lint,
- the requests fallback exists before `filterAndMergeApproverRequests()`,
- the final header contains the route-scoped server-side login class,
- the final Docker image contains all three fixes,
- raw `/session/login` HTML already contains `<body class="hayne-login-target">` before client-side enhancement,
- native `CI_Email` embeds the real PNG bytes in the inline attachment.

## Acceptance

- accepting/rejecting from the review flow must no longer produce the `requests` undefined-key / null TypeError chain,
- loading the login page must not visibly paint the legacy split login before the HAYNE card,
- a newly generated real notification e-mail must render the supplied HAYNE logo in Outlook,
- absence-policy registry remains 20 rows with unchanged workflow semantics,
- no unrelated runtime scope drift.

## Production

No production deployment is part of this implementation branch. Production deployment remains a separate guarded QNAP rebuild/recreate after merge and green CI.
