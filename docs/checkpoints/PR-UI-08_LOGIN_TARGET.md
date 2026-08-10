# PR-UI-08 — HAYNE login target

## Cel

Doprowadzić publiczny ekran `/session/login` do zaakceptowanej makiety `minimalistyczny_ekran_logowania_hayne_leave.png` i zamknąć podstawową ścieżkę wizualną HAYNE od logowania do dashboardu i wniosków.

## Acceptance target

Makieta definiuje split-screen: biały panel brandowy po lewej z logo i liniową ilustracją, jasne tło po prawej oraz centralną kartę logowania z logo HAYNE, tytułem `HAYNE Leave`, opisem, polami z ikonami i czarnym CTA.

## Zakres

- `assets/hayne/login.css` — izolowany split-screen i geometria karty,
- `assets/hayne/login.js` — prezentacyjne ulepszenie istniejącego formularza: polskie copy, placeholdery, inline SVG w polach oraz toggle widoczności hasła,
- `assets/hayne/login-illustration.svg` — monochromatyczna ilustracja lampy, rośliny i kalendarza zgodna z językiem dashboardu,
- `010-header-branding.patch` — ładowanie assetów targetu,
- bez zmian endpointu ani mechanizmu uwierzytelniania.

## Guardrails

- zachować `#loginFrom`, `name=login`, `name=password`, `language`, `last_page` i CSRF,
- zachować `session/login`, istniejącą walidację, obsługę LDAP/OAuth/SAML i redirect po logowaniu,
- nie dodawać atrap `Zapamiętaj mnie` ani `Nie pamiętasz hasła?`, ponieważ upstream nie udostępnia dla nich publicznego flow w tym formularzu,
- toggle hasła jest wyłącznie lokalną funkcją klienta i nie zmienia wartości pola,
- nie modyfikować authenticated shell,
- realny screenshot CI musi zostać ręcznie porównany z targetem przed merge.
