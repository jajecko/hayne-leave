# PR-REGRESSION-01 — review action, login first paint and mail logo

Date: 2026-08-17
Status: implementation reviewed; dedicated regression CI passed; final full CI pending after checkpoint update

## Reported production regressions

1. After accepting a leave from the review flow opened from e-mail, `/requests/requested` can fail with:
   - `Undefined array key "requests"` in `Requests.php`,
   - followed by `TypeError` because `filterAndMergeApproverRequests()` receives `null` instead of `array`.
2. The legacy Jorani login layout is visible briefly before the HAYNE login target appears.
3. Real notification e-mails show a broken/missing HAYNE logo even though the CID code is present.

## Root causes

### Requests list

Patch 285 calls the strictly typed `filterAndMergeApproverRequests(array $managerRequests, ...)` with `$data['requests']` directly. Production evidence shows that the key can be absent on the post-review redirect path. The upstream manager-query methods themselves declare `: array`; the fix therefore keeps workflow-model typing strict and initializes `$data['requests']` to an empty array before the existing history/non-history query branch.

### Login first paint

The final login layout is scoped to `body.hayne-login-target`, while `login.js` previously added that class only from its deferred client enhancement after parsing. This allowed the legacy server-rendered form to be painted briefly.

The initial implementation attempted to set the body class from the global header. Execution review rejected that approach because the new header hunk did not independently apply to the pinned upstream source. The final patch is narrower: `session/login.php` synchronously adds `hayne-login-target` immediately after the opening PHP block, before the legacy inline `<style>` and before any visible login markup. The existing deferred `login.js` remains idempotent and continues to add icons, placeholders and password-toggle behavior.

### Mail logo

Patch 286 previously called:

`attach($mailLogoPath, 'inline', 'hayne-logo.png', 'image/png')`

In the pinned CodeIgniter `CI_Email::attach()` implementation, supplying a non-empty MIME argument makes the first argument be treated as already-buffered file content. Because the first argument was a filesystem path, the attachment contained the text of the path rather than the PNG bytes. The CID existed, but the referenced payload was invalid.

The fix calls:

`attach($mailLogoPath, 'inline', 'hayne-logo.png')`

CodeIgniter now opens the file, reads the PNG bytes, derives `image/png`, and `attachment_cid()` marks the attachment multipart/related.

## Runtime changes

- `hayne/patches/286-ui-mail-branding-hotfix.patch`
  - remove the explicit fourth MIME argument from the inline-logo `attach()` call.
- `hayne/patches/287-review-login-regression-hotfix.patch`
  - initialize `$data['requests'] = []` before the manager request query branch,
  - add the synchronous `hayne-login-target` hook at the top of `session/login.php`, before login styles and markup.

No database, registry, entitlement, leave type, HR-routing, AD, PWA, push-routing or production-configuration changes are in scope.

## Review sequence

The required sequence was followed:

1. plan,
2. review plan,
3. patch,
4. review patch.

Execution review then found and corrected test/integration issues without expanding runtime scope:

- redundant `(array)` casts were removed because upstream manager-query methods already return `array`,
- the initial global-header login hunk was rejected by independent dry-run and replaced with the login-view synchronous hook,
- a quoting error in the login-order CI assertion was corrected,
- the diagnostic harness was hardened so patch stderr is surfaced directly and teardown cannot hide the primary failure.

## Deterministic verification

### Existing UI/mail workflow

`.github/workflows/verify-pr-ui-mail-branding-hotfix.yml` now:

- requires the corrected three-argument `attach()` call,
- rejects the broken four-argument form,
- instantiates native `CI_Email`,
- attaches the actual runtime `logo.png`,
- obtains a CID,
- reads the private attachment payload,
- base64-decodes it,
- compares its SHA-256 byte-for-byte with the actual PNG,
- requires MIME `image/png`, multipart `related`, and matching CID.

### Dedicated regression workflow

`.github/workflows/verify-pr-review-login-regressions.yml` verifies:

- patch 287 independently applies to pristine Jorani v1.0.4,
- the complete overlay + patch stack applies,
- final regression targets lint,
- the requests fallback occurs before `filterAndMergeApproverRequests()`,
- the login hook occurs before the legacy `<style>` and visible login markup,
- the final Docker image builds and starts,
- raw `/session/login` response orders the synchronous hook before the HAYNE login shell,
- native `CI_Email` embeds the real PNG bytes,
- final runtime retains HAYNE workflow wiring.

Dedicated run on head `94b90d11f9c7a5246af3b73997bcb6084b777092` passed all steps, including build, raw-login order, real PNG MIME payload and built-runtime contracts.

## Acceptance

- accepting/rejecting from the review flow must no longer produce the `requests` undefined-key / null TypeError chain,
- loading the login page must not visibly paint the legacy split login before the HAYNE card,
- a newly generated real notification e-mail must render the supplied HAYNE logo in Outlook,
- absence-policy registry remains 20 rows with unchanged workflow semantics,
- no unrelated runtime scope drift.

## Production

No production deployment is part of this PR. Production deployment remains a separate guarded QNAP rebuild/recreate after merge and green final CI.
