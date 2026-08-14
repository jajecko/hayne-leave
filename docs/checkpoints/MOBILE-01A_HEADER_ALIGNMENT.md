# MOBILE-01A — Mobile header alignment

## Problem
On a real phone after MOBILE-01 deployment, the hamburger button rendered below the HAYNE logo, centered in the header instead of staying on the same row at the right edge.

## Root cause
`app.css` defines `#wrap > .navbar .navbar-inner` as a flex container with `flex-direction: column` for the desktop sidebar. The mobile shell changed sizing/alignment but did not reset the inherited flex direction, so the logo and hamburger remained stacked vertically.

## Fix
In the existing `@media (max-width: 979px)` rule in `hayne/overlay/assets/hayne/shell.css`, reset `.navbar-inner` to `flex-direction: row`.

## Scope / guardrails
- CSS-only.
- Authenticated mobile/tablet shell only (`<=979px`).
- No JavaScript, PHP, backend, leave logic, PWA, notifications, database, or desktop shell changes.
- Desktop `>=980px` remains unchanged.

## Expected result
The compact mobile header renders the HAYNE logo on the left and the 44x44 hamburger control on the right in a single 72px row. The off-canvas drawer behavior from MOBILE-01 remains unchanged.
