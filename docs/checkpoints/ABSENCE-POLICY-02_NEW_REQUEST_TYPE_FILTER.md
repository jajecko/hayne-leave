# ABSENCE-POLICY-02 — new request type filter

Status: implementation checkpoint
Date: 2026-08-17

## Goal

Consume the central `hayne_leave_type_registry` only on the new leave-request path and hide the MVP legacy/out-of-domain types from new submissions:

- 2 — on-demand legacy type (HAYNE uses vacation type + request metadata instead),
- 17 — legacy Saturday-holiday compensation type,
- 18 — delegation recovery type pending a business rule,
- 19 — Home Office, which belongs to work status rather than absence.

## Scope

- `Leaves::create()` loads `Hayne_leave_type_registry_model`.
- Both GET/form-render and POST/server-validation use the same active type IDs from the registry.
- The employee contract list is intersected by registry IDs, never by display name.
- If the configured default type is no longer available after filtering, the first active contract type becomes the form default.
- The existing Jorani POST allow-list check remains the server-side enforcement point; a crafted POST for a hidden type is therefore forced away from that hidden type.
- Filtering activates only when the audited HAYNE `types` catalog signature matches and the registry returns active IDs. This preserves upstream/fresh installations where the HAYNE production catalog is not present.

## Explicit non-goals

- no delete/update of rows in `types`,
- no change to historical leave rows,
- no filtering in `Leaves::edit()`, so an existing historical request can still display its original type,
- no changes to `entitleddays`,
- no changes to `deduct_days_off`,
- no new balance/workflow rules for types 3–16,
- no Home Office module,
- no production deployment in this PR.

## Acceptance criteria

1. Registry IDs marked `active_for_new_requests=0` are excluded from the create-form type array.
2. The same filtered array is used by POST validation, preventing a hidden ID from being accepted through a crafted request.
3. `Leaves::edit()` does not consume the new-request filter.
4. Controller code contains no hardcoded list `2/17/18/19`; those decisions remain in the central registry.
5. Full cumulative patch stack applies to Jorani v1.0.4.
6. Final runtime PHP lints successfully and Docker image builds.
7. Registry map still contains 20 MVP entries and exactly 16 active LEAVE types for new requests.
