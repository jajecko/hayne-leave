# PR-UI-14 — Enterprise UX dla administracji limitami

Data: 2026-08-13

## Cel

Przebudować ekran `Limity urlopowe` z długiej, mieszanej strony administracyjnej na kompaktowy ekran zadaniowy dla HR, bez zmiany istniejącej logiki limitów i rozliczeń.

## Architektura informacji

Ekran dzieli się na trzy zakładki:

1. `Przydzielanie limitów` — grupowy przydział rocznej puli.
2. `Uprawnienia ustawowe` — polityki ustawowe jako kompaktowe pozycje rozwijane na żądanie.
3. `Pracownicy` — wyjątki pojedynczych pracowników, korekta wykorzystania i diagnostyka FIFO.

Selektor roku pozostaje globalny w nagłówku strony.

## Przydzielanie limitów

Widok otrzymuje układ enterprise master/detail:

- lewa kolumna: wyszukiwarka, filtry i tabela pracowników;
- prawa kolumna: ustawienia grupowego przydziału;
- kompaktowe KPI nad tabelą filtrują pracowników;
- bez wewnętrznego pionowego scrolla tabeli;
- `Koryguj wykorzystanie` pozostaje akcją wiersza zgodną z istniejącym PR-LEAVE-09;
- nie dodajemy kolumny `Nowy limit`, ponieważ nie odpowiada ona aktualnemu modelowi operacyjnemu.

Tabela pokazuje:

- pracownika,
- stan konfiguracji,
- roczny limit,
- wykorzystanie,
- pozostałe dni,
- akcję `Koryguj wykorzystanie`.

## Bezpieczeństwo bulk assignment

Zachowana jest istniejąca semantyka backendu:

- domyślnie istniejące profile są pomijane;
- jawny tryb `Nadpisz istniejące` ustawia istniejący `overwrite_existing=1`;
- profile używające innego rodzaju urlopu są nadal bezpiecznie pomijane;
- grupowa edycja jest dostępna tylko dla bieżącego roku;
- `annual_days`, `vacation_type_id`, `auto_renew` i `employee_ids[]` zachowują istniejące nazwy i kontrakt formularza.

Presety 20/26 i własny wymiar zmieniają bezpośrednio pole `annual_days`, więc zachowany jest fix odporności z PR-UI-13.

## Uprawnienia ustawowe

Dotychczasowe formularze polityk nie są przepisywane. Są osadzone w natywnych elementach `details/summary`, dzięki czemu:

- domyślny widok jest krótki;
- stan włączone/wyłączone jest widoczny bez otwierania formularza;
- szczegóły prawne i konfiguracja są ujawniane dopiero na żądanie;
- istniejące endpointy i field names pozostają bez zmian.

## Pracownicy

Tryb pojedynczej edycji przeniesiono do osobnej zakładki. Lista profili pokazuje najważniejsze wartości i dwie realne akcje:

- `Edytuj` — wyjątek konfiguracji profilu;
- `Koryguj wykorzystanie` — istniejący mechanizm ręcznego rozliczenia urlopów papierowych.

Rozbicie FIFO jest domyślnie ukryte w `details`, ponieważ jest diagnostyką, a nie podstawowym zadaniem operatora.

## Guardrails

Brak zmian w:

- modelach sald i FIFO,
- tabelach bazy danych,
- endpointach zapisu limitów,
- korektach wykorzystania,
- AD,
- kalendarzu dni wolnych,
- akceptacji i anulowaniu wniosków,
- polityce pełnych dni.

## Zmienione pliki

- `hayne/overlay/legacy/application/views/haynelimits/index.php`
- `hayne/overlay/legacy/application/views/haynelimits/bulk.php`
- `hayne/overlay/assets/hayne/limits.css`
- `hayne/overlay/assets/hayne/limits.js`
- `.github/workflows/verify-pr-leave-bulk.yml`

## Akceptacja

- ekran jest znacznie krótszy i bardziej kompaktowy;
- trzy główne zadania HR są rozdzielone na zakładki;
- główny bulk workflow mieści konfigurację obok tabeli zamiast nad nią;
- tabela nie zawiera fikcyjnej kolumny `Nowy limit`;
- każdy pracownik ma akcję `Koryguj wykorzystanie`;
- formularz zachowuje bezpieczne pomijanie i jawne nadpisywanie;
- polityki ustawowe są domyślnie zwinięte;
- FIFO jest domyślnie zwinięte;
- istniejące testy backendowe create/skip/overwrite nadal przechodzą;
- PHP widoków przechodzi lint, a zasoby CSS/JS są dostępne w runtime.
