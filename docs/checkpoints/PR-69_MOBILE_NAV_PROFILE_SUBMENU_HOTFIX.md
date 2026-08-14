# PR-69 — Mobile navigation profile/submenu hotfix

## Problem
Na mobilnym drawerze po rozwinięciu `Do akceptacji` linki submenu nie reagowały poprawnie na dotyk, a karta profilu nachodziła na pierwszą pozycję menu i zmieniała położenie wraz z rozwijaniem grup.

## Root cause
- profil jest zbudowany na legacy `a.brand`, które dziedziczy bootstrapowe `float:left`; w mobilnym drawerze rodzic nie obejmował wysokości karty i kolejny element wchodził pod profil;
- `navigation.js` synchronicznie zamykał cały drawer w bubbling `click` każdego zwykłego linku, co na mobilnym touch mogło kolidować z aktywacją linku w bootstrapowym dropdownie.

## Fix
- mobilny override resetuje float profilu i stabilizuje jego udział w normalnym flow;
- submenu dostaje jawne touch/pointer zachowanie;
- realne linki w `.dropdown-menu` zatrzymują bubbling na poziomie samego linku, bez `preventDefault`, dzięki czemu natywna nawigacja wykonuje się przed resetem draweru;
- poprawka działa wyłącznie poniżej 980 px; desktop pozostaje bez zmian.

## Scope
Runtime:
- `hayne/overlay/assets/hayne/mobile-navigation-hotfix.css`
- `hayne/overlay/assets/hayne/mobile-navigation-hotfix.js`
- `hayne/patches/010-header-branding.patch`

CI:
- `.github/workflows/verify-pr-mobile-navigation-hotfix.yml`

Bez zmian w logice urlopów, saldach/FIFO, uprawnieniach, manager/delegate, AD, SMTP, Web Push, DB i workflow akceptacji.
