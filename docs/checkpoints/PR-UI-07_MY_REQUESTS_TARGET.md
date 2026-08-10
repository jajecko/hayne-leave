# PR-UI-07 — HAYNE My Requests target

## Cel

Doprowadzić `/leaves` do zaakceptowanej makiety `panel_moje_wnioski_w_hayne_leave.png`, bez zmiany kontraktu danych i logiki filtrowania DataTables.

## Acceptance target

Makieta pokazuje: pusty topbar z user-chipem, nagłówek `Moje wnioski`, CTA `Nowy wniosek`, statusowe zakładki, wyszukiwarkę, `Filtry`, tabelę w kolejności `Typ / Data od / Data do / Liczba dni / Status / Akcje` i kompaktową paginację.

## Strategia techniczna

Nie zmieniamy indeksów danych używanych przez istniejący skrypt Jorani. `requests.js` działa po inicjalizacji DataTables i buduje targetowy toolbar, proxy statusów i menu akcji. `requests-list.css` układa istniejące komórki tabeli przez CSS Grid w docelowej kolejności, ukrywając tylko prezentacyjne kolumny `Powód`, `Requested`, `Last change`; oryginalne indeksy `type=5` i `status=6` pozostają bez zmian dla filtrów.

## Guardrails

- zachować `#leaves`, `#cboLeaveType`, wszystkie `.filterStatus` i ich ID,
- zachować `filterStatusColumn()` i istniejące filtrowanie URL `type` / `statuses`,
- zachować action URLs edit/delete/cancel/reminder/view/history,
- nie usuwać eksportu ani ICS — zostają przeniesione do panelu `Filtry`,
- nie tworzyć przykładowych wierszy ani statusów,
- brak zmian kontrolerów, modeli, bazy i endpointów,
- realny screenshot CI musi zostać ręcznie porównany z targetem przed merge.
