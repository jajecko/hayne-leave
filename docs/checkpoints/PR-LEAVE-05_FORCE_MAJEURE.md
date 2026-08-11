# PR-LEAVE-05 — Siła wyższa 2 dni

## Cel

Dodać do HAYNE Leave zwolnienie od pracy z powodu działania siły wyższej jako osobną roczną pulę 2 dni, bez mieszania jej z urlopem wypoczynkowym, urlopem na żądanie lub urlopem opiekuńczym i bez tworzenia drugiego silnika sald obok Jorani.

## Podstawa wymagań

Źródłem wymagań prawnych dla tego slice jest przekazany do projektu tekst Kodeksu pracy, stan Kancelarii Sejmu 2026-07-28, art. 1481.

Dla PR-LEAVE-05 implementujemy potwierdzone wymagania art. 1481 w zakresie możliwym w przyjętym whole-day-only produkcie HAYNE:

- 2 dni albo 16 godzin w roku kalendarzowym,
- przyczyna: działanie siły wyższej w pilnych sprawach rodzinnych spowodowanych chorobą lub wypadkiem,
- konieczna jest niezbędna natychmiastowa obecność pracownika,
- o sposobie wykorzystania — dni albo godziny — pracownik decyduje w pierwszym wniosku w danym roku,
- pracodawca udziela zwolnienia na wniosek zgłoszony najpóźniej w dniu korzystania,
- za czas zwolnienia pracownik zachowuje prawo do połowy wynagrodzenia.

## Whole-day-only i wariant godzinowy

HAYNE nie dodaje obsługi godzin ani części dnia.

W tym slice:

- wniosek złożony w HAYNE jest zawsze w pełnych dniach,
- pierwszy wniosek złożony przez pracownika w HAYNE w danym roku oznacza wybór wariantu dniowego,
- wariant 16-godzinny pozostaje poza HAYNE i jest obsługiwany przez HR innym kanałem,
- HAYNE nie importuje ani nie odejmuje wykorzystania godzinowego wykonanego poza systemem.

Konsekwencja operacyjna: jeżeli pracownik wybrał w danym roku wariant godzinowy poza HAYNE, HR nie powinien pozwolić na korzystanie przez niego z puli dniowej HAYNE w tym samym roku. Automatyczna synchronizacja takiej zewnętrznej decyzji jest poza zakresem PR-LEAVE-05.

## Wynagrodzenie

UI pokazuje informację, że za okres tego zwolnienia przysługuje połowa wynagrodzenia. HAYNE nie nalicza wynagrodzeń i nie modyfikuje żadnego modułu płacowego.

## Architektura

### Saldo

Jorani `entitleddays` pozostaje źródłem prawdy dla przyznanego kredytu.

HAYNE tworzy idempotentny rekord pracownik/rok/typ:

`[HAYNE_STATUTORY|force_majeure|YYYY] = 2 dni`

Ta pula:

- jest odrębna od wypoczynkowej,
- jest odrębna od urlopu opiekuńczego,
- nie korzysta z FIFO urlopu wypoczynkowego,
- nie jest przenoszona na kolejny rok,
- w następnym roku powstaje nowa pula dokładnie 2 dni.

### Mapowanie typu

Nie hardcodujemy produkcyjnego ID rodzaju nieobecności.

Istniejąca tabela `hayne_statutory_leave_policies` przechowuje mapowanie `force_majeure -> leave_type_id`. Administrator/HR wybiera istniejący rodzaj nieobecności na stronie `Limity urlopowe`.

HAYNE blokuje przypisanie tego samego aktywnego typu Jorani do więcej niż jednej zarządzanej puli HAYNE oraz blokuje wykorzystanie typu będącego już typem urlopu wypoczynkowego. Zapobiega to podwójnemu naliczaniu i niejednoznacznej walidacji.

### Dane wniosku

Tabela `hayne_force_majeure_request_meta` przechowuje minimalne dane potwierdzające zastosowanie polityki do konkretnego `leave_id`:

- `event_code`: `illness` albo `accident`,
- `immediate_presence`: potwierdzenie, że natychmiastowa obecność pracownika jest niezbędna.

Nie przechowujemy diagnozy, danych medycznych członka rodziny ani rozbudowanego opisu zdarzenia, ponieważ art. 1481 nie wymaga ich w tym slice, a HAYNE nie powinien zbierać nadmiarowych danych wrażliwych.

### Limit i termin

Dla statusów Requested / Accepted / Cancellation HAYNE rezerwuje limit roczny.

Planned nie rezerwuje limitu, ale przejście Planned -> Requested ponownie wykonuje walidację.

Wniosek Requested/Accepted:

- może zostać złożony w dniu rozpoczęcia zwolnienia,
- nie może zostać złożony po rozpoczęciu zwolnienia,
- musi mieścić się w limicie 2 dni,
- nie może obejmować dwóch lat kalendarzowych,
- jest zawsze liczony w pełnych dniach.

Kontrola limitu jest wykonywana serwerowo i serializowana blokadą rocznej puli pracownika (`SELECT ... FOR UPDATE`) w tej samej transakcji co zapis wniosku/metadanych.

## UI

### Administracja -> Limity urlopowe

Dodana sekcja `Siła wyższa`:

- wybór istniejącego typu Jorani,
- włączenie/wyłączenie automatycznej polityki,
- stały wymiar 2 dni,
- informacja o braku carry-over,
- informacja o whole-day-only i obsłudze godzinowej poza HAYNE,
- informacja o 50% wynagrodzenia bez logiki płacowej.

### Nowy / edycja wniosku

Po wybraniu skonfigurowanego typu pokazują się:

- wybór `Choroba` / `Wypadek`,
- wymagane potwierdzenie niezbędnej natychmiastowej obecności,
- informacja o pozostałym limicie,
- informacja, że wniosek można złożyć najpóźniej w dniu korzystania,
- informacja o whole-day-only i zewnętrznym wariancie godzinowym.

### Saldo

Widoczny jest osobny wiersz typu zwolnienia z dodatkowym znacznikiem HAYNE, wykorzystaniem/rezerwacją X/2 i pozostałą liczbą dni.

### Szczegóły wniosku

Dane polityki są widoczne read-only dla osób, które mają dostęp do danego wniosku.

## Guardraile

- whole-day only; PR nie dodaje obsługi godzin ani połówek dnia,
- brak zmian w urlopie wypoczynkowym / FIFO / carry-over,
- brak zmian w logice 4 dni urlopu na żądanie,
- brak zmian w urlopie opiekuńczym 5 dni poza współdzielonym zabezpieczeniem kolizji typu i zapewnieniem puli przed stockowym credit check,
- brak zmian w approval endpoints/statusach,
- brak logiki płacowej,
- brak zgadywania ID typu produkcyjnego,
- każdy nowy patch musi samodzielnie przejść dry-run na pristine Jorani v1.0.4,
- Docker nadal aplikuje patche sekwencyjnie.

## Weryfikacja

Dedykowany workflow `verify-pr-leave-05` sprawdza:

1. konfigurację mapowania typu i idempotentną pulę 2 dni,
2. blokadę kolizji jednego typu pomiędzy ustawowymi politykami,
3. widoczność formularza i informacji o trybie dniowym,
4. odrzucenie braku potwierdzenia natychmiastowej obecności,
5. akceptację wniosku złożonego tego samego dnia,
6. odrzucenie wniosku dotyczącego dnia już minionego,
7. wykorzystanie pierwszego i drugiego dnia,
8. odrzucenie trzeciego dnia,
9. ponowną walidację terminu Planned -> Requested,
10. ponowną walidację pełnego limitu Planned -> Requested,
11. widoczność metadanych w szczegółach i saldzie,
12. nową pulę dokładnie 2 dni w następnym roku, bez carry-over.

## Poza zakresem

- wariant godzinowy 16 godzin i jego proporcjonalne rozliczanie,
- synchronizacja wykorzystania godzinowego prowadzonego poza HAYNE,
- art. 188: 2 dni / 16 godzin opieki nad dzieckiem,
- macierzyński / rodzicielski / ojcowski / wychowawczy,
- naliczanie płac i świadczeń,
- automatyczne wyliczanie wymiaru wypoczynkowego 20/26 na podstawie stażu,
- produkcyjne przypisanie konkretnego `leave_type_id` — robi to administrator przez UI.
