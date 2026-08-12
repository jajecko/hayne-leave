# PR-UI-11 — My requests status tabs

## Problem

The HAYNE status tabs on `leaves` rendered correctly but did not reliably drive the legacy Jorani status filter or update their active visual state.

The custom enhancer changed the hidden legacy checkboxes and then used jQuery `.trigger('change')`. HAYNE's own tab-state listener is registered with native `addEventListener`, so the synthetic jQuery event did not provide one consistent event path for both systems.

## Fix

`hayne/overlay/assets/hayne/requests.js` now:

- updates the existing legacy status checkboxes,
- dispatches a native bubbling `change` event from `#chkPlanned`,
- lets the existing Jorani jQuery handler run `filterStatusColumn()`,
- lets the HAYNE native listener synchronize `is-active` / `aria-pressed`,
- immediately synchronizes the clicked preset as an additional deterministic UI update.

No DataTables filter semantics or leave status business logic are replaced.

## Acceptance

- `Wszystkie` selects all six legacy status checkboxes and shows all statuses.
- `Oczekujące` selects only requested.
- `Zaakceptowane` selects only accepted.
- `Plan` selects only planned.
- `Odrzucone` selects only rejected.
- clicked preset receives `is-active` and `aria-pressed=true`.
- the DataTable redraws using upstream `filterStatusColumn()`.
- advanced status checkboxes under `Filtry` remain compatible.
- no backend, leave workflow, DB, calendar or AD changes.
