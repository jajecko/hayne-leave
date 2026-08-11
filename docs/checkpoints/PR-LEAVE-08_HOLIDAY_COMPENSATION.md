# PR-LEAVE-08 — dzień wolny za święto

## Cel

Obsłużyć w HAYNE operacyjny dzień wolny wynikający z obniżenia wymiaru czasu pracy przez święto przypadające w dniu innym niż niedziela, bez mieszania tego mechanizmu z urlopem wypoczynkowym.

## Podstawa i granice produktu

Art. 130 § 2 Kodeksu pracy stanowi, że każde święto występujące w okresie rozliczeniowym i przypadające w innym dniu niż niedziela obniża wymiar czasu pracy o 8 godzin. Państwowa Inspekcja Pracy wskazuje, że przy standardowym rozkładzie poniedziałek–piątek święto przypadające w sobotę wymaga odpowiedniego obniżenia wymiaru, zwykle przez wyznaczenie innego dnia wolnego w tym samym okresie rozliczeniowym.

HAYNE nie próbuje sam rozstrzygać, czy konkretnemu pracownikowi należy przyznać osobny dzień wolny. Przy systemach równoważnych lub innych harmonogramach obniżenie wymiaru może zostać rozliczone w grafiku inaczej. Decyzję o utworzeniu grantu podejmuje HR.

## Model HAYNE

- osobna polityka `holiday_compensation`,
- dedykowany typ Jorani `Dzień wolny za święto` (`DWS`),
- typ jest tworzony idempotentnie, bez hardcodowania ID,
- brak rekordów `entitleddays`,
- brak wpływu na urlop wypoczynkowy 20/26, FIFO i urlop na żądanie,
- każdy grant daje dokładnie 1 pełny dzień,
- grant jest przypisany do pracownika, święta źródłowego i granic okresu rozliczeniowego,
- jeden pracownik może mieć wiele grantów z różnych świąt,
- ten sam pracownik nie może mieć dwóch grantów dla tej samej daty święta.

## Dane grantu

Tabela `hayne_holiday_compensation_grants`:

- `employee_id`,
- `source_holiday_date`,
- `period_start`,
- `period_end`,
- znaczniki czasu.

Unikalność: `(employee_id, source_holiday_date)`.

Tabela `hayne_holiday_compensation_request_meta` łączy wniosek Jorani z `grant_id`.

## Zasady wykorzystania

- wniosek musi obejmować dokładnie 1 pełny dzień,
- data wolnego musi mieścić się w `period_start..period_end`,
- `Requested`, `Accepted` i `Cancellation` rezerwują grant,
- `Planned` przechowuje grant, lecz go nie rezerwuje,
- `Planned -> Requested` ponownie waliduje grant pod blokadą transakcyjną,
- `Rejected` i `Canceled` nie rezerwują grantu,
- po wysłaniu wniosku nie wolno przepiąć go do innego grantu,
- grant nie przechodzi na kolejny okres rozliczeniowy; granice okresu są twardą walidacją daty wniosku.

## Współbieżność

Przed rezerwacją HAYNE wykonuje `SELECT ... FOR UPDATE` na konkretnym rekordzie grantu i utrzymuje transakcję do zapisu wniosku oraz metadanych. Pozwala to uniknąć równoczesnego wykorzystania tego samego grantu przez dwa wnioski.

## Typ nieobecności

HAYNE wyszukuje typ po dokładnej nazwie `Dzień wolny za święto`. Jeżeli go nie ma, dodaje rekord do `types` przez AUTO_INCREMENT z akronimem `DWS` i `deduct_days_off=1`. Nie zakładamy produkcyjnego ID ani nie przywracamy usuniętego historycznego typu `compensate`.

## Administracja

Nowa strona `/hayneholidays` pozwala HR/adminowi:

1. przygotować/aktywować dedykowany typ,
2. ręcznie przyznać pracownikowi grant,
3. podać datę święta źródłowego,
4. podać początek i koniec okresu rozliczeniowego,
5. zobaczyć istniejące granty i ich stan rezerwacji.

`/haynelimits` zawiera wejście do tej strony, ale granty nie są prezentowane jako roczna pula urlopowa.

## Whole-day-only

HAYNE obsługuje ten mechanizm wyłącznie jako 1 pełny dzień. PR nie dodaje obsługi godzin ani połówek dnia.

## Poza zakresem

- automatyczny kalendarz świąt,
- automatyczne ustalanie uprawnienia z harmonogramu czasu pracy,
- generowanie grafiku,
- automatyczne ustalanie długości okresu rozliczeniowego,
- logika płacowa,
- produkcyjne wdrożenie na NAS.

## Weryfikacja

Workflow `verify-pr-leave-08` sprawdza m.in.:

1. idempotentne utworzenie dedykowanego typu bez hardcodowanego ID,
2. aktywację polityki bez `entitleddays`,
3. ręczny grant dla święta 15.08.2026 w sierpniowym okresie rozliczeniowym,
4. widoczność grantu na formularzu pracownika,
5. odrzucenie daty poza okresem,
6. odrzucenie wniosku 2-dniowego,
7. poprawny wniosek 1-dniowy przy zerowym stockowym kredycie Jorani,
8. blokadę ponownego wykorzystania grantu,
9. szczegóły święta i okresu na widoku wniosku,
10. rewalidację `Planned -> Requested`,
11. brak wpływu na roczne pule urlopowe.
