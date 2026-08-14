# MOBILE-01B — Drawer interaction and centered mobile header

## Real-device findings
After MOBILE-01/MOBILE-01A deployment on a real phone:
1. the drawer rendered visually below the dim backdrop and its controls could not be clicked;
2. the preferred mobile header composition is a centered HAYNE Leave logo with the hamburger anchored on the right.

## Root cause: drawer interaction
The mobile navbar has `z-index: 1030` and creates the stacking context containing `.nav-responsive`. The backdrop is appended outside that navbar and had `z-index: 1040`. A child drawer with `z-index: 1050` cannot escape the navbar's 1030 stacking context, so the backdrop still painted above the whole navbar subtree and intercepted pointer input.

## Fix
In the final mobile target layer (`<=979px`):
- lower `.hayne-mobile-menu-overlay` to `z-index: 1020`, below the navbar stacking context;
- explicitly disable overlay pointer events while closed and enable them only while the drawer is open;
- keep the drawer inside the existing navbar/navigation DOM, with no cloning and no JS state changes.

## Header composition
- `.navbar-inner` becomes a relative positioning context and centers its normal-flow content;
- HAYNE brand is centered;
- hamburger is absolutely anchored to the right and vertically centered;
- symmetric horizontal padding reserves space for the control so the logo remains optically centered and cannot collide with it.

## Scope / guardrails
- CSS-only;
- authenticated mobile/tablet shell only (`<=979px`);
- desktop `>=980px` unchanged;
- no JS, PHP, backend, database, leave logic, PWA, routes or notification changes.

## Expected result
- logo centered in the compact mobile header;
- hamburger remains on the right;
- opened drawer stays visually above the backdrop and all drawer/profile/navigation controls are clickable;
- backdrop still dims the page and closes the drawer when tapped outside it.
