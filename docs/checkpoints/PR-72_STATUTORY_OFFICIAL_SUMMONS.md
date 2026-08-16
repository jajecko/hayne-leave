# PR #72 — STATUTORY-01 official summons without entitlement credit

## Problem

The HAYNE leave type used for `wezwanie do sądu / urzędu / innego organu` has no annual entitlement pool. Jorani v1.0.4 applies the global `DISALLOW_REQUESTS_WITHOUT_CREDIT` rule to every leave type, so a one-day request with credit `0` is rejected with `Przekraczasz przysługujący limit dni`.

The browser reproduces the same incorrect assumption: `leaves/validate` always returns a numeric credit and stock `leave.edit-1.0.4.js` compares request duration to that credit.

## Scope

This PR adds one explicit statutory mapping: `official_summons` -> one existing Jorani `leave_type_id`.

The mapping is stored in the existing `hayne_statutory_leave_policies` table. Runtime code does not infer the policy from a translated/display name and does not hard-code a production leave type ID.

## Runtime behaviour

When the selected leave type is the enabled `official_summons` mapping:

- create and edit skip only the entitlement-credit rejection;
- `leaves/validate` returns `creditExempt: true` and omits numeric `credit`;
- the HAYNE create form hides the stock credit warning and renders `Dostępne saldo: nie dotyczy`;
- date validation, contract boundaries, overlap checks, non-working-day calculation, status rules and the HAYNE full-day policy remain active.

For every other leave type, native credit validation remains unchanged.

## Administration

`Limity urlopowe -> Uprawnienia ustawowe` gets a new disclosure:

`Wezwanie sądu / urzędu / innego organu`

HR/admin explicitly selects the existing Jorani leave type and enables `Nie wymagaj salda dla tego rodzaju nieobecności`.

Collision guards prevent the same type from simultaneously being used as the HAYNE vacation type or another active statutory policy.

## Files

- `hayne/overlay/legacy/application/models/Hayne_credit_exemption_model.php`
- `hayne/overlay/legacy/application/views/haynelimits/official_summons.php`
- `hayne/overlay/assets/hayne/official-summons.js`
- `hayne/patches/279-official-summons-credit-exemption.patch`
- `hayne/patches/280-official-summons-admin.patch`
- `hayne/patches/281-official-summons-assets.patch`
- `.github/workflows/verify-pr-statutory-summons.yml`

## Explicit non-scope

- no global change to `DISALLOW_REQUESTS_WITHOUT_CREDIT`;
- no automatic matching by leave type name;
- no hard-coded leave type ID;
- no hourly/partial-day implementation;
- no changes to caregiver/force-majeure/childcare/occasion/holiday-compensation limits;
- no manager-review, alert or mail-logo changes in this PR;
- no production deployment in the PR itself.

## Required smoke after deployment

1. In `Limity urlopowe -> Uprawnienia ustawowe`, map the correct existing type to `Wezwanie sądu / urzędu / innego organu` and enable the policy.
2. Open `Nowy wniosek`, select that type and confirm `Dostępne saldo: nie dotyczy`.
3. Submit one full day while the Jorani balance for the type is `0` — request must be created.
4. Confirm manager receives the normal request notification.
5. Select a credit-backed vacation type with balance `0` and request one day — submission must still be rejected for insufficient balance.
6. Verify overlap and contract-boundary errors still block invalid summons requests.
7. Switch repeatedly between summons and vacation types — credit label/warning must follow the selected type without stale UI state.

## QNAP deploy gate

Because two PR #71 files were previously observed as `0 B` after copying into `/share/Container/jorani/app/hayne`, deployment must use the hardened gate:

1. compare extracted SOURCE to `/share/Container/jorani/app/hayne` before build (`diff -qr` must be empty);
2. verify critical new files are non-zero and SHA256-identical to SOURCE;
3. build the app image;
4. inspect the built image before `force-recreate` and verify the model, JS and patched controller/header content;
5. only then recreate service `app`.
