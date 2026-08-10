# PR-UI-04 — HAYNE target dashboard

## Cel

Doprowadzić zalogowaną aplikację HAYNE Leave do zaakceptowanego kierunku wizualnego: jasny dashboard, stały lewy sidebar, Figtree jako domyślna typografia, duży nagłówek `Panel`, hero, trzy karty podsumowania oraz dolne karty `Nadchodzące nieobecności` i `Szybkie akcje`.

## Acceptance target

Referencją wizualną jest screen przekazany przez użytkownika 2026-08-10. Nie traktujemy go jako luźnej inspiracji — kolejne iteracje są oceniane względem tej kompozycji.

## Guardrails

- bez zmian logiki urlopowej i approval workflow,
- bez zmian kontrolerów, modeli i bazy danych,
- bez wymyślania danych KPI; jeśli Home nie ma prawdziwej wartości, UI używa uczciwego CTA,
- zachować wszystkie istniejące linki i dropdowny administracyjne,
- Figtree ma być domyślnym fontem UI,
- realne screenshoty CI muszą zostać obejrzane przed merge.

## Zakres

- globalna warstwa `typography.css` z Figtree,
- wspólne prymitywy UI w `foundation.css`,
- targetowy sidebar i topbar w `shell.css`,
- enhancer `navigation.js` tworzący bezpośrednie skróty Start / Nowy wniosek / Moje wnioski / Saldo urlopowe przy zachowaniu legacy dropdownów,
- przebudowa Home w `local/pages/en/home.php`,
- nowy dashboard styling w `home.css`.

## Weryfikacja

PR nie może zostać zmergowany wyłącznie na podstawie zielonego CI. Ostateczna decyzja wymaga porównania screenshotu Home z acceptance targetem oraz sprawdzenia regresji na `leaves`, `balance` i `create-leave`.
