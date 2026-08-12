# PR-PHP85-01 — Email helper bool casts

## Problem
Submitting a leave request on PHP 8.5 emits deprecation notices from `legacy/application/helpers/MY_email_helper.php` because the upstream helper uses the non-canonical `(boolean)` cast. The notices are rendered before the redirect and then trigger a secondary `Cannot modify header information - headers already sent` warning.

Observed affected upstream lines: 83, 97, 115, 128.

## Scope
Replace only the four `(boolean)` casts in `MY_email_helper.php` with canonical `(bool)` casts through a Hayne patch.

## Runtime effect
No mail flow, leave workflow, business rules, database schema, AD authentication, or UI behavior is changed. This is a PHP 8.5 compatibility fix only.

## Acceptance criteria
- all four deprecated `(boolean)` casts are replaced by `(bool)`;
- image build can apply all Hayne patches cleanly;
- `MY_email_helper.php` passes PHP syntax validation after patch application;
- submitting a leave request no longer renders the PHP 8.5 deprecation notices from this helper;
- redirect after request submission is no longer broken by those notices.
