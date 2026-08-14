# MOBILE-01A — Mobile shell real-device fixes

## Problems found on real phone
1. The hamburger rendered below the HAYNE logo, centered in the header instead of staying on the same row at the right edge.
2. After opening the drawer, the primary navigation fell back to legacy Bootstrap/two-column styling instead of the intended single-column HAYNE mobile menu.

## Root causes
### Header
`app.css` defines `#wrap > .navbar .navbar-inner` as a flex container with `flex-direction: column` for the desktop sidebar. The mobile shell changed sizing/alignment but did not reset the inherited flex direction, so the logo and hamburger remained stacked vertically.

### Drawer navigation
The HAYNE shell CSS identifies the primary navigation as `.nav-responsive > .nav:first-child`. MOBILE-01 inserted the new drawer header with `navResponsive.prepend(drawerHeader)`, which made that header the first child. As soon as JavaScript ran, the primary `<ul class="nav">` stopped matching the HAYNE selectors and inherited legacy Bootstrap/mobile-grid presentation.

## Fixes
- In the existing `@media (max-width: 979px)` rule in `hayne/overlay/assets/hayne/shell.css`, reset `.navbar-inner` to `flex-direction: row`.
- Append `.hayne-mobile-drawer-header` to `.nav-responsive` instead of prepending it. Its existing `order: -30` keeps it visually first in the drawer while preserving the primary navigation as the first DOM child and therefore preserving all existing HAYNE selectors.

## Scope / guardrails
- Authenticated mobile/tablet shell fix.
- One CSS declaration and one DOM insertion-order change.
- No PHP, backend, leave logic, PWA, notifications, database, routes, or business logic changes.
- No navigation cloning and no duplicate menu state.
- Desktop navigation selectors remain valid because the primary nav keeps its original DOM position.

## Expected result
- Compact mobile header: HAYNE logo on the left and 44x44 hamburger on the right in one 72px row.
- Drawer header and profile at the top.
- Primary navigation rendered as the intended single-column HAYNE menu, not the legacy two-column Bootstrap grid.
- Group dropdowns remain inline in the drawer.
