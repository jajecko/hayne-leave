# PR-LEAVE-04 — Urlop opiekuńczy 5 dni

## Cel

Dodać do HAYNE Leave ustawowy urlop opiekuńczy jako osobną roczną pulę 5 dni, bez mieszania go z urlopem wypoczynkowym i bez tworzenia drugiego silnika sald obok Jorani.

## Podstawa wymagań

Źródłem wymagań prawnych dla tego slice jest przekazany do projektu tekst Kodeksu pracy, stan Kancelarii Sejmu 2026-07-28, art. 1731–1733.

Dla PR-LEAVE-04 implementujemy wyłącznie wymagania wynikające z art. 1731:

- 5 dni w roku kalendarzowym,
- urlop służy osobistej opiece lub wsparciu osoby wymagającej tego z poważnych względów medycznych,
- członek rodziny w tym przepisie: syn, córka, matka, ojciec lub małżonek,
- alternatywnie osoba zamieszkująca z pracownikiem w tym samym gospodarstwie domowym,
- urlop jest udzielany w dni pracy pracownika,
- wniosek papierowy lub elektroniczny składa się co najmniej 1 dzień przed rozpoczęciem,
- wniosek zawiera imię i nazwisko osoby, przyczynę opieki/wsparcia i odpowiednio stopień pokrewieństwa albo adres zamieszkania osoby z tego samego gospodarstwa domowego.

Nie kodujemy w tym PR zasad wynagrodzenia ani świadczeń, ponieważ nie są one częścią potwierdzonego zakresu tego slice.

## Architektura

### Saldo

Jorani `entitleddays` pozostaje źródłem prawdy dla przyznanego kredytu.

HAYNE tworzy idempotentny rekord pracownik/rok/typ:

`[HAYNE_STATUTORY|caregiver|YYYY] = 5 dni`

Ta pula:

- jest odrębna od wypoczynkowej,
- nie korzysta z FIFO urlopu wypoczynkowego,
- nie jest przenoszona na kolejny rok,
- w następnym roku powstaje nowa pula dokładnie 5 dni.

### Mapowanie typu

Nie hardcodujemy produkcyjnego ID rodzaju nieobecności.

Tabela `hayne_statutory_leave_policies` przechowuje mapowanie `caregiver -> leave_type_id`. Administrator/HR wybiera istniejący rodzaj nieobecności na stronie `Limity urlopowe`.

### Dane wniosku

Tabela `hayne_caregiver_request_meta` przechowuje dane ustawowe powiązane przez `leave_id`:

- `person_name`,
- `relation_code`,
- `household_address`,
- `care_reason`.

Dopuszczalne relacje:

- `son`,
- `daughter`,
- `mother`,
- `father`,
- `spouse`,
- `household`.

Dla `household` adres jest obowiązkowy.

### Limit

Dla statusów Requested / Accepted / Cancellation HAYNE rezerwuje limit roczny.

Planned nie rezerwuje limitu, ale przejście Planned -> Requested ponownie wykonuje walidację.

Kontrola 5 dni jest wykonywana serwerowo i serializowana blokadą rocznej puli pracownika (`SELECT ... FOR UPDATE`) w tej samej transakcji co zapis wniosku/metadanych.

## UI

### Administracja -> Limity urlopowe

Dodana sekcja `Urlop opiekuńczy`:

- wybór istniejącego typu Jorani,
- włączenie/wyłączenie automatycznej polityki,
- stały wymiar 5 dni,
- informacja o braku carry-over.

### Nowy / edycja wniosku

Po wybraniu skonfigurowanego typu pokazują się pola:

- osoba wymagająca opieki lub wsparcia,
- relacja,
- adres dla osoby z tego samego gospodarstwa,
- przyczyna opieki/wsparcia,
- informacja o pozostałym limicie i terminie co najmniej 1 dnia.

### Saldo

Widoczny jest osobny wiersz `Urlop opiekuńczy` z wykorzystaniem/rezerwacją X/5 i pozostałą liczbą dni.

### Szczegóły wniosku

Dane ustawowe są widoczne w read-only view wniosku dla osób mających dostęp do tego wniosku.

## Guardraile

- whole-day only; PR nie dodaje obsługi godzin,
- brak zmian w urlopie wypoczynkowym / FIFO / carry-over,
- brak zmian w logice 4 dni urlopu na żądanie,
- brak zmian w approval endpoints/statusach,
- brak zgadywania ID typu produkcyjnego,
- każdy nowy patch musi samodzielnie przejść dry-run na pristine Jorani v1.0.4,
- Docker nadal aplikuje patche sekwencyjnie.

## Weryfikacja

Dedykowany workflow `verify-pr-leave-04` sprawdza:

1. konfigurację mapowania typu i idempotentną pulę 5 dni,
2. widoczność pól formularza,
3. odrzucenie braku adresu dla `household`,
4. odrzucenie wniosku składanego tego samego dnia,
5. rezerwację pierwszych 3 dni,
6. odrzucenie kolejnych 3 dni przy pozostałych 2,
7. dojście do 5/5 przez poprawny wniosek 2-dniowy,
8. odrzucenie 6. dnia,
9. ponowną walidację Planned -> Requested,
10. zapis i widoczność metadanych,
11. nową pulę dokładnie 5 dni w następnym roku, bez carry-over.

## Poza zakresem

- art. 188: 2 dni / 16 godzin opieki nad dzieckiem,
- art. 1481: siła wyższa 2 dni / 16 godzin,
- macierzyński / rodzicielski / ojcowski / wychowawczy,
- naliczanie płac i świadczeń,
- automatyczne wyliczanie wymiaru wypoczynkowego 20/26 na podstawie stażu,
- produkcyjne przypisanie konkretnego `leave_type_id` — robi to administrator przez UI.
