# PR-LEAVE-06 — Opieka nad dzieckiem do 14 lat (art. 188)

## Cel

Dodać do HAYNE Leave zwolnienie od pracy z art. 188 Kodeksu pracy jako osobną roczną pulę pracownika, bez mieszania jej z urlopem wypoczynkowym, urlopem na żądanie, urlopem opiekuńczym ani siłą wyższą.

## Podstawa wymagań

Źródłem podstawowym jest przekazany do projektu tekst Kodeksu pracy, stan Kancelarii Sejmu 2026-07-28, art. 188 i art. 1891.

Dodatkowo zakres operacyjny zweryfikowano na aktualnych materiałach Państwowej Inspekcji Pracy z 2026 r. PIP potwierdza m.in.:

- 2 dni albo 16 godzin w roku kalendarzowym,
- wybór sposobu wykorzystania w pierwszym wniosku danego roku,
- zachowanie prawa do wynagrodzenia,
- możliwość podziału uprawnienia pomiędzy rodziców, np. po 1 dniu, przy zachowaniu łącznego limitu,
- brak carry-over na kolejny rok,
- brak ustawowego sztywnego terminu „dzień wcześniej”; przy nagłej potrzebie możliwe jest zgłoszenie w dniu korzystania.

## Whole-day-only

HAYNE pozostaje systemem wyłącznie pełnodniowym.

Pierwszy wniosek art. 188 złożony w HAYNE w danym roku oznacza wybór wariantu dniowego. Wariant godzinowy pozostaje poza HAYNE i jest obsługiwany przez HR.

Jeżeli pracownik wybrał wariant godzinowy poza HAYNE, HR powinien ustawić jego limit dniowy HAYNE na 0 dla danego roku.

## Architektura

### Mapowanie typu

Globalne mapowanie `childcare -> leave_type_id` jest przechowywane w istniejącej tabeli `hayne_statutory_leave_policies`.

Administrator wybiera dedykowany istniejący rodzaj nieobecności Jorani. HAYNE nie hardcoduje produkcyjnego ID.

Ten sam typ nie może być jednocześnie używany jako:

- urlop wypoczynkowy,
- urlop opiekuńczy,
- siła wyższa,
- opieka nad dzieckiem do 14 lat.

### Roczny limit pracownika

Nowa tabela:

`hayne_childcare_year_allocations`

przechowuje wyłącznie:

- `employee_id`,
- `year`,
- `granted_days` = 1 albo 2.

Brak rekordu oznacza 0 dni w HAYNE na dany rok.

Dzięki temu:

- nie przechowujemy danych dziecka,
- HR może przypisać pełne 2 dni,
- HR może przypisać 1 dzień przy podziale uprawnienia między rodziców,
- HR może pozostawić 0 dni, jeżeli pracownik nie korzysta z wariantu dniowego lub nie ma potwierdzonego uprawnienia.

### Jorani entitleddays

Jorani pozostaje źródłem prawdy dla kredytu widocznego w standardowym mechanizmie sald.

Dla dodatniej alokacji HAYNE tworzy idempotentnie:

`[HAYNE_STATUTORY|childcare|YYYY]`

z dokładną wartością 1 albo 2 dni.

Nie ma carry-over i nie ma automatycznego tworzenia puli w kolejnym roku bez jawnej alokacji HR.

### Rezerwacja limitu

Requested / Accepted / Cancellation rezerwują limit.

Planned nie rezerwuje limitu, ale Planned -> Requested ponownie wykonuje walidację i blokadę puli.

Kontrola jest serializowana przez `SELECT ... FOR UPDATE` na rocznym rekordzie `entitleddays` w tej samej transakcji co zapis/zmiana wniosku.

### Brak dodatkowych danych wniosku

Art. 188 nie wymaga od HAYNE gromadzenia danych dziecka ani szczególnej przyczyny wniosku. Wniosek identyfikuje dedykowany rodzaj nieobecności, pracownika, daty, status i zwykłe pole `cause` Jorani.

HAYNE świadomie nie tworzy tabeli metadanych dziecka.

## UI

### Administracja -> Limity urlopowe

Sekcja `Opieka nad dzieckiem do 14 lat` zawiera:

- wybór dedykowanego typu Jorani,
- włączenie/wyłączenie polityki,
- wybór roku,
- listę aktywnych pracowników,
- limit `0 / 1 / 2 dni` dla każdego pracownika.

### Nowy / edycja wniosku

Po wybraniu skonfigurowanego typu pokazuje się panel:

- przyznany limit na rok,
- pozostała liczba dni,
- informacja o braku carry-over,
- informacja, że HAYNE obsługuje wariant dniowy,
- informacja, że wariant godzinowy jest poza HAYNE.

Brak alokacji daje czytelny komunikat o konieczności kontaktu z HR; serwer również odrzuca taki wniosek.

### Saldo

Dla pracownika z dodatnią alokacją wiersz ma znacznik `data-hayne-childcare-summary="v1"` oraz pokazuje wykorzystanie/rezerwację i pozostałą liczbę dni.

## Guardraile

- whole-day only,
- brak danych dziecka w bazie,
- brak automatycznego zgadywania uprawnienia,
- brak automatycznego 2-dniowego limitu dla wszystkich pracowników,
- brak carry-over,
- brak zmian w wypoczynkowym/FIFO/na żądanie,
- brak zmian w urlopie opiekuńczym i sile wyższej poza wspólną kontrolą kolizji typu,
- brak logiki godzinowej i płacowej,
- brak sztucznego terminu „1 dzień wcześniej”,
- każdy patch musi samodzielnie przejść dry-run na pristine Jorani v1.0.4.

## Weryfikacja

Workflow `verify-pr-leave-06` sprawdza:

1. konfigurację mapowania typu bez hardcodowania produkcyjnego ID,
2. brak automatycznej puli bez alokacji pracownika,
3. blokadę kolizji typu z inną polityką HAYNE,
4. przypisanie 1 dnia jako wariantu podziału między rodziców,
5. utworzenie dokładnie 1 dnia w `entitleddays`,
6. widoczność panelu na formularzu,
7. odrzucenie wniosku 2-dniowego przy alokacji 1 dnia,
8. poprawny wniosek 1-dniowy złożony tego samego dnia,
9. blokadę obniżenia alokacji poniżej już zarezerwowanego wykorzystania,
10. zwiększenie alokacji 1 -> 2 i aktualizację `entitleddays`,
11. dojście do 2/2 i odrzucenie 3. dnia,
12. ponowną walidację Planned -> Requested przy pełnym limicie,
13. saldo 2/2,
14. brak automatycznego carry-over do kolejnego roku,
15. jawne utworzenie nowej puli 2 dni dopiero po zapisaniu alokacji na kolejny rok.

## Poza zakresem

- wariant 16 godzin,
- automatyczne ustalanie wieku dziecka,
- przechowywanie imienia, daty urodzenia lub innych danych dziecka,
- automatyczna synchronizacja wykorzystania przez drugiego rodzica,
- naliczanie wynagrodzenia,
- pozostałe urlopy rodzicielskie i macierzyńskie.
