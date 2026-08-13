# PR-LEAVE-08 — Canceled request overlap

Status: implementation ready for CI
Date: 2026-08-13

## Problem

A leave request that reached terminal status `LMS_CANCELED` remained in `leaves` for audit/history, but `Leaves_model::detectOverlappingLeaves()` excluded only rejected status `4`. A new request for the same dates was therefore incorrectly rejected as overlapping with the canceled request.

## Decision

Canceled requests remain in the database. HAYNE Leave uses status transitions and history rather than hard-deleting submitted/processed requests.

Terminal statuses that do not reserve calendar time:

- `LMS_REJECTED`
- `LMS_CANCELED`

Statuses that continue to block overlap:

- `LMS_PLANNED`
- `LMS_REQUESTED`
- `LMS_ACCEPTED`
- `LMS_CANCELLATION` (cancellation requested but not yet completed)

This keeps the workflow conservative: an accepted leave still reserves the period while its cancellation is awaiting approval. Once the cancellation reaches `LMS_CANCELED`, the period is free for a new request.

## Change

`detectOverlappingLeaves()` now uses:

```php
$this->db->where_not_in('status', [LMS_REJECTED, LMS_CANCELED]);
```

instead of the numeric `status != 4` condition.

## Guardrails

No changes to:

- request deletion rules,
- approval/cancellation transitions,
- leave balance accounting,
- AD authentication,
- calendar/dayoffs synchronization,
- database schema or existing data.

## Acceptance

- rejected requests do not block a new request for the same period;
- fully canceled requests do not block a new request for the same period;
- pending cancellation still blocks until cancellation is approved;
- active/planned/requested/accepted requests still block overlap;
- patched image builds and `Leaves_model.php` passes PHP lint.
