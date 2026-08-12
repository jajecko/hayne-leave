# PR-LEAVE-FORM-01 — formularz nowego wniosku / pola polityk HAYNE

## Problem

Na `/leaves/create` po włączeniu urlopu na żądanie blok `Urlop na żądanie` był renderowany wielokrotnie — raz przy kolejnych pozycjach listy rodzajów nieobecności. Powodowało to również rozpad wizualny selektora rodzaju nieobecności i całego formularza.

## Przyczyna

`hayne/patches/205-on-demand-views.patch` wstawiał blok urlopu na żądanie po pojedynczej linii `</select>`. Taki hunk nie miał wystarczającego kontekstu. W sekwencyjnym buildzie GNU `patch` mógł zastosować go z fuzzem po utracie całego istotnego kontekstu i wkleić blok wewnątrz iteracji renderującej typy nieobecności.

Dodatkowo `assets/hayne/request.js` przebudowuje formularz tworząc własny layout, ale dotąd przenosił tylko stockowe kontrolki Jorani. Pola kolejnych polityk HAYNE pozostawały poza kontrolowanym layoutem, a `on-demand.js` wykonywał osobną operację reparentingu.

## Zmiana

- `205-on-demand-views.patch` dla create/edit kotwiczy blok bezpośrednio przed kompletnym selektorem rodzaju nieobecności, używając całego niezmienionego bloku `<select>`, `foreach`, `endforeach` i `</select>` jako kontekstu. Dzięki temu źródłowy blok polityki nie może trafić do iteratora typów;
- finalne położenie w formularzu create jest kontrolowane przez `request.js`: wszystkie pola polityk HAYNE są przenoszone jako oryginalne elementy DOM do jednego `data-hayne-policy-fields="v1"` bez klonowania i umieszczane bezpośrednio pod polem `Rodzaj nieobecności`;
- grupowane pola to:
  - `hayneOnDemandOption`,
  - `hayneCaregiverFields`,
  - `hayneForceMajeureFields`,
  - `hayneChildcareFields`,
  - `hayneOccasionFields`,
  - `hayneHolidayCompensationFields`;
- `on-demand.js` nie konkuruje z redesignem create o położenie elementu. Na stockowym ekranie edit normalizuje tylko położenie pojedynczego bloku on-demand bezpośrednio po selektorze typu;
- hunk podsumowania on-demand w `counters.php` jest zakotwiczony na wspólnej, stabilnej granicy `</thead> / <tbody> / count($summary)`, dzięki czemu działa zarówno na pristine v1.0.4, jak i po nałożeniu pełnego override salda HAYNE;
- wzmocniono również kontekst hunka flash.

## Niezmienniki formularza

- w finalnym `/leaves/create` istnieje dokładnie jeden `id="hayneOnDemandOption"`;
- blok on-demand nigdy nie znajduje się wewnątrz `<select id="type">` ani pętli renderującej typy;
- wszystkie fragmenty polityk HAYNE mają jeden kontrolowany obszar pod rodzajem nieobecności;
- elementy są przenoszone, a nie klonowane, więc nie powstają duplikaty ID ani pól formularza.

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
- sprawdza finalny zbudowany kod create/edit i wymaga zamknięcia iteratora typów wewnątrz kompletnego selektora,
- odrzuca runtime z `Undefined variable`, `Fatal error`, `Uncaught` lub `Parse error`.

Na finalnym kodzie przed merge wymagane są zielone:
- `verify`,
- `verify-pr-leave-03`,
- `verify-pr-leave-form-layout`.

## Produkcja

Po merge wymagany jest rebuild samego kontenera aplikacji na QNAP. Brak migracji bazy danych. Po wdrożeniu wymagany jest wizualny smoke `/leaves/create` na rzeczywistym koncie przed zamknięciem regresji UI.
