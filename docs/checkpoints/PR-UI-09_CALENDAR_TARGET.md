# PR-UI-09 — HAYNE individual calendar

## Cel

Doprowadzić `/calendar/individual` do tego samego systemu wizualnego co dashboard, `Nowy wniosek`, `Moje wnioski`, `Saldo urlopowe` i login. Dla kalendarza nie ma osobnej zaakceptowanej makiety, więc projekt ma być bezpośrednią kontynuacją istniejącego HAYNE design systemu, bez tworzenia nowego języka UI.

## Zakres

- HAYNE header `Kalendarz`, opis i CTA `Nowy wniosek`,
- jedna duża karta kalendarza,
- toolbar `Poprzedni / Dzisiaj / Następny`, `Dni wolne`, `ICS`,
- legenda `Plan / Zaakceptowane / Oczekujące / Odrzucone / Dzień wolny`,
- polskie nazwy miesięcy i dni w FullCalendar,
- monochromatyczne warianty zdarzeń zależne od istniejącego koloru statusu,
- stylowanie month grid, dnia bieżącego, weekendów, modali i ICS,
- osobny screenshot CI `hayne-calendar.png`.

## Guardrails

- bez zmian `Calendar` controller i `Leaves_model`,
- źródło zdarzeń pozostaje `leaves/individual`,
- źródło dni wolnych pozostaje `contracts/calendar/userdayoffs`,
- event click nadal otwiera istniejące pobranie `ics/ical/<id>`,
- ICS feed nadal korzysta z `ics/individual/<user>?token=<random_hash>`,
- zachować istniejący algorytm half-day (`startdatetype` / `enddatetype`) i rerender przy resize,
- zachować AJAX timeout/error handling,
- nie tworzyć przykładowych wydarzeń ani danych,
- bez zmian bazy, workflow urlopowego i endpointów.

## Acceptance

Finalny `hayne-calendar.png` musi zostać ręcznie obejrzany w viewport CI 1440×1000. Kalendarz ma być czytelny jako część HAYNE Leave, bez legacy Bootstrap/Jorani chrome. Regression screenshots podstawowych ekranów pozostają obowiązkowe przed merge.
