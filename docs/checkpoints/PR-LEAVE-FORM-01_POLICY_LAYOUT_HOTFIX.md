# PR-LEAVE-FORM-01 — formularz nowego wniosku / pola polityk HAYNE

## Problem

Na `/leaves/create` po włączeniu urlopu na żądanie blok `Urlop na żądanie` był renderowany wielokrotnie — raz przy kolejnych pozycjach listy rodzajów nieobecności. Powodowało to również rozpad wizualny selektora rodzaju nieobecności i całego formularza.

## Przyczyna

`hayne/patches/205-on-demand-views.patch` wstawiał blok urlopu na żądanie po pojedynczej linii `</select>`. Taki hunk nie miał wystarczającego kontekstu. W sekwencyjnym buildzie GNU `patch` mógł zastosować go z fuzzem w niewłaściwym miejscu, wewnątrz iteracji renderującej typy nieobecności.

Dodatkowo `assets/hayne/request.js` przebudowuje formularz tworząc własny layout, ale dotąd przenosił tylko stockowe kontrolki Jorani. Pola kolejnych polityk HAYNE pozostawały poza kontrolowanym layoutem, a `on-demand.js` wykonywał osobną operację reparentingu.

## Zmiana

- `205-on-demand-views.patch` używa wieloliniowego, unikalnego kontekstu całego selektora typu dla create i edit; blok on-demand jest dodawany dopiero po zakończeniu `foreach` i `</select>`.
- wzmocniono również kontekst hunków flash/counters w tym patchu;
- `request.js` zbiera wszystkie znane pola polityk HAYNE w jeden `data-hayne-policy-fields="v1"` bez klonowania elementów:
  - `hayneOnDemandOption`,
  - `hayneCaregiverFields`,
  - `hayneForceMajeureFields`,
  - `hayneChildcareFields`,
  - `hayneOccasionFields`,
  - `hayneHolidayCompensationFields`;
- `on-demand.js` nie wykonuje już konkurencyjnego reparentingu DOM; odpowiada wyłącznie za widoczność i stan checkboxa.

## Zakres

Hotfix nie zmienia:
- naliczania 20/26 dni,
- FIFO i zaległego urlopu,
- limitu 4 dni urlopu na żądanie,
- limitów ustawowych,
- DWS i jego grantów,
- statusów wniosków,
- schematu SQL.

## Test regresyjny

`verify-pr-leave-form-layout`:
- sprawdza składnię JS,
- buduje pełny obraz HAYNE,
- sprawdza składnię finalnych view create/edit,
- aktywuje profil urlopu wypoczynkowego, aby blok on-demand był faktycznie renderowany,
- wymaga dokładnie jednego `hayneOnDemandOption` w odpowiedzi `/leaves/create`,
- potwierdza, że blok nie znajduje się wewnątrz `<select id="type">`,
- sprawdza w finalnym zbudowanym źródle, że `foreach` typów i `</select>` zamykają się przed blokiem polityki,
- odrzuca runtime z `Undefined variable`, `Fatal error`, `Uncaught` lub `Parse error`.

## Produkcja

Po merge wymagany jest rebuild samego kontenera aplikacji na QNAP. Brak migracji bazy danych.
