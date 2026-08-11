# PR-LEAVE-07 — urlop okolicznościowy

## Cel

Dodać do HAYNE Leave zwolnienie od pracy określane potocznie jako urlop okolicznościowy zgodnie z § 15 rozporządzenia Ministra Pracy i Polityki Socjalnej z 15 maja 1996 r.

## Źródła wymagań

- ELI: Dz.U. 1996 nr 60 poz. 281, akt posiada tekst jednolity.
- Państwowa Inspekcja Pracy: „Urlop wypoczynkowy i inne zwolnienia od pracy”, sekcja „Urlopy okolicznościowe”.
- PIP: odpowiedź dotycząca śmierci i pogrzebu macochy małżonka, z pełnym katalogiem § 15.

## Model HAYNE

Urlop okolicznościowy nie jest roczną pulą. HAYNE nie tworzy dla niego rekordów `entitleddays` i nie pomniejsza urlopu wypoczynkowego.

Każdy wniosek ma minimalne metadane:

- `event_code` — kategoria zdarzenia,
- `event_date` — kanoniczna data zdarzenia.

Dla zdarzeń związanych ze zgonem i pogrzebem `event_date` oznacza datę zgonu. Dzięki temu dwa rozdzielone dni należne z jednego zdarzenia są rozliczane razem nawet wtedy, gdy drugi dzień jest wykorzystywany w związku z pogrzebem.

HAYNE nie przechowuje imienia/nazwiska członka rodziny ani kopii dokumentów. Dokument i związek terminu wolnego ze zdarzeniem są weryfikowane organizacyjnie przez przełożonego lub HR.

## Limity na zdarzenie

### 2 dni

- ślub pracownika,
- urodzenie dziecka pracownika,
- zgon i pogrzeb małżonka / małżonki,
- zgon i pogrzeb dziecka,
- zgon i pogrzeb ojca,
- zgon i pogrzeb matki,
- zgon i pogrzeb ojczyma,
- zgon i pogrzeb macochy.

### 1 dzień

- ślub dziecka pracownika,
- zgon i pogrzeb siostry,
- zgon i pogrzeb brata,
- zgon i pogrzeb teściowej,
- zgon i pogrzeb teścia,
- zgon i pogrzeb babki,
- zgon i pogrzeb dziadka,
- zgon i pogrzeb innej osoby pozostającej na utrzymaniu pracownika,
- zgon i pogrzeb innej osoby pozostającej pod bezpośrednią opieką pracownika.

Dwa dni jednego zdarzenia mogą być wykorzystane w jednym albo dwóch wnioskach. HAYNE sumuje aktywne wykorzystanie po `(employee_id, event_code, event_date)`.

## Statusy

- `Requested`, `Accepted`, `Cancellation` rezerwują limit konkretnego zdarzenia.
- `Planned` przechowuje metadane, ale nie rezerwuje limitu.
- przejście `Planned -> Requested` ponownie sprawdza limit pod blokadą transakcyjną.

## Współbieżność

Tabela `hayne_occasion_events` posiada klucz główny `(employee_id, event_code, event_date)`. Przed walidacją rezerwacji HAYNE wykonuje `SELECT ... FOR UPDATE` na rekordzie konkretnego zdarzenia i utrzymuje transakcję do zapisu wniosku/metadanych.

## Integracja z Jorani

Globalna polityka `occasion -> leave_type_id` korzysta z dedykowanego istniejącego typu Jorani. Produkcyjne ID nie jest hardcodowane.

Ponieważ `DISALLOW_REQUESTS_WITHOUT_CREDIT=TRUE`, stockowy credit check Jorani jest pomijany wyłącznie dla skonfigurowanego typu `occasion`. Autorytatywnym ograniczeniem jest wtedy limit HAYNE na konkretne zdarzenie. Nie tworzymy sztucznego kredytu `999` ani rocznej puli 1/2 dni.

## Whole-day-only

HAYNE obsługuje urlop okolicznościowy wyłącznie w pełnych dniach. Model odrzuca niecałkowitą lub zerową długość po serwerowym przeliczeniu Jorani.

## Termin względem zdarzenia

Nie wprowadzamy arbitralnego okna typu ±7 dni. Przepisy wskazują zdarzenie uprawniające i wymiar, ale nie definiują takiego sztywnego okna. Związek terminu zwolnienia ze zdarzeniem pozostaje elementem akceptacji HR/przełożonego.

## Pliki runtime

- `hayne/overlay/legacy/application/models/Hayne_occasion_leave_model.php`
- `hayne/overlay/legacy/application/views/leaves/occasion_fields.php`
- `hayne/overlay/legacy/application/views/leaves/occasion_details.php`
- `hayne/overlay/legacy/application/views/haynelimits/occasion.php`
- `hayne/overlay/legacy/application/controllers/Haynelimits.php`
- `hayne/overlay/legacy/application/views/haynelimits/index.php`
- `hayne/sql/001-leave-profiles.sql`
- patche `240`–`244`

## Guardraile

- brak rocznej puli dla urlopu okolicznościowego,
- brak wpływu na wypoczynkowy/FIFO/urlop na żądanie,
- brak danych członka rodziny i uploadu dokumentów,
- brak automatycznego okna czasowego względem zdarzenia,
- brak logiki godzinowej,
- brak zmian płacowych,
- dedykowany typ nie może kolidować z urlopem wypoczynkowym ani innymi politykami HAYNE,
- każdy patch musi niezależnie dry-runować na pristine Jorani v1.0.4,
- produkcyjny NAS pozostaje poza zakresem tego PR.

## Weryfikacja

Workflow `verify-pr-leave-07` sprawdza m.in.:

1. konfigurację polityki bez tworzenia annual entitlement,
2. kolizję typu z inną polityką,
3. obecność pól zdarzenia na formularzu,
4. działanie wniosku przy zerowym stockowym kredycie Jorani,
5. dwa rozdzielone dni jednego zdarzenia i blokadę trzeciego,
6. oddzielenie dwóch zdarzeń tego samego rodzaju po dacie,
7. pojedynczy wniosek 2-dniowy dla zdarzenia 2-dniowego,
8. odrzucenie 2 dni dla zdarzenia 1-dniowego,
9. edycję metadanych planowanego wniosku,
10. ponowną walidację `Planned -> Requested`,
11. widok szczegółów,
12. brak rekordów `[HAYNE_STATUTORY|occasion|...]` w `entitleddays`.
