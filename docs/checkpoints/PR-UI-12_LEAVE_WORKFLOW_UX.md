# PR-UI-12 — Leave workflow UX polish

Status: implementation ready for CI
Date: 2026-08-13

## Scope

This slice addresses three concrete issues observed in the HAYNE Leave workflow:

1. Manager approvals list wastes a full column on a separate end date and can render that slot as visually empty.
2. Fully canceled requests remain visible in the individual calendar and fall through to the rejected/red presentation.
3. Leave request view/edit screens still use the legacy Bootstrap layout with weak spacing, narrow controls and poor hierarchy.

## Decisions

### Approvals list

- Keep original DataTables columns in the DOM so sorting/filtering contracts remain intact.
- Present start + end date as one `Termin` column.
- Strip obsolete `Rano` / `Po południu` suffixes from the manager table because HAYNE uses whole-day leave policy.
- Hide the original end-date presentation column only at the HAYNE presentation layer.
- Rebalance the grid to six visible columns: Pracownik, Typ, Termin, Dni, Status, Akcje.

### Individual calendar

- Keep canceled requests in the database for audit/history.
- Exclude only final `LMS_CANCELED` rows from `Leaves_model::individual()`.
- Keep `LMS_CANCELLATION` visible because the absence is still effective while cancellation is pending.
- Render pending cancellation with the accepted/active color rather than the rejected fallback.

### Leave view/edit UX

- Do not replace the upstream `leaves/view.php` / `leaves/edit.php` templates; several HAYNE policy patches already extend them.
- Add a progressive enhancement layer that activates only on leave view/edit pages.
- Preserve all form field names, IDs, submit values, comments/history markup and server-side behavior.
- Use a responsive two-card desktop layout and one-column mobile layout.
- Use consistent HAYNE control height, spacing, borders, typography and action hierarchy.
- Hide day-part selectors on view/edit because the active HAYNE policy is whole days only; values stay in the form/DOM for compatibility.
- Make read-only status compact instead of presenting it as a large editable-looking control.

## Files

Runtime:

- `hayne/overlay/assets/hayne/approvals-term.css`
- `hayne/overlay/assets/hayne/approvals-term.js`
- `hayne/overlay/assets/hayne/leave-detail.css`
- `hayne/overlay/assets/hayne/leave-detail.js`
- `hayne/patches/261-calendar-hide-canceled.patch`
- `hayne/patches/262-leave-workflow-ux-assets.patch`

Verification:

- `.github/workflows/verify-pr-ui12-leave-workflow-ux.yml`

## Guardrails

No changes to:

- leave deletion/audit policy,
- approval/cancellation transitions,
- leave balances or FIFO pools,
- AD authentication/provisioning,
- managed work calendar/dayoffs synchronization,
- database schema,
- notification delivery,
- create-request business semantics.

## Acceptance

- approvals table has no empty date column and shows a useful `Termin` range;
- rejected/canceled history remains in DB/list views;
- final canceled requests are absent from the personal calendar;
- pending cancellation remains visible until approved;
- request view/edit pages have consistent cards, spacing, full-width controls and responsive layout;
- comments/history are visually grouped and readable;
- whole-day policy no longer exposes morning/afternoon selectors on view/edit;
- existing form IDs/names/actions remain untouched;
- patched image builds and model passes PHP lint.
