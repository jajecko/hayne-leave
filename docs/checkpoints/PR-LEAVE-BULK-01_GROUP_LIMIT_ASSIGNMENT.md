# PR-LEAVE-BULK-01 — Grupowe przydzielanie limitów urlopowych

## Cel

Usunąć konieczność konfigurowania rocznego limitu wypoczynkowego pracownik po pracowniku. Główny workflow HR ma pozwalać zaznaczyć wiele aktywnych osób, ustawić jeden wymiar i zapisać go jednym działaniem, bez przebudowy istniejącego silnika sald.

## Architektura

Bulk jest wyłącznie warstwą administracyjną nad istniejącym `Hayne_leave_policy_model::saveProfile()`.

Nie zmieniamy:

- modelu `hayne_leave_profiles`,
- markerów `HAYNE_POOL`,
- FIFO,
- rolloveru,
- sposobu liczenia wykorzystania,
- istniejącego endpointu edycji pojedynczej.

Nowy endpoint `haynelimits/save-bulk` jest obsługiwany przez osobny kontroler `Haynebulklimits`, dostępny tylko dla HR/admin.

## UX

Główny picker pokazuje wszystkich aktywnych pracowników, także osoby bez profilu HAYNE.

Operator ma do dyspozycji:

- checkbox per pracownik,
- zaznaczenie wszystkich aktualnie widocznych pozycji,
- wyszukiwanie po imieniu i nazwisku,
- filtry `Wszyscy`, `Bez limitu`, `Skonfigurowani`,
- presety 20 i 26 dni,
- dowolny pełnodniowy wymiar,
- przełącznik automatycznego odnawiania,
- licznik zaznaczonych osób,
- podgląd aktualnego limitu, wykorzystania i salda dla wybranego roku.

Interfejs jest responsywny, tabela ma przewijalny obszar i sticky nagłówek, a pasek akcji pozostaje widoczny przy długiej liście.

## Bezpieczeństwo masowej operacji

Domyślnie istniejące profile są pomijane. Operator musi świadomie zaznaczyć `Aktualizuj także osoby z istniejącym limitem`, aby zmienić ich wymiar lub ustawienie automatycznego odnawiania.

Nawet wtedy bulk nie zmienia rodzaju urlopu dla istniejącego profilu. Jeżeli zaznaczona osoba ma inny `vacation_type_id`, zostaje pominięta i wymaga istniejącej edycji pojedynczej. Zapobiega to przypadkowemu przepięciu profilu posiadającego już zarządzane pule.

Dodatkowe guardraile serwera:

- tylko aktywni istniejący pracownicy,
- poprawny istniejący typ urlopu,
- 0–366 pełnych dni,
- maksymalnie 500 unikalnych osób w jednym żądaniu,
- kontrola kolizji z aktywnymi politykami ustawowymi,
- częściowy błąd jednego pracownika nie zatrzymuje pozostałych; błąd trafia do logu i do licznika wyniku.

## Frontend

Nowe zasoby:

- `assets/hayne/limits.css`,
- `assets/hayne/limits.js`,
- partial `haynelimits/bulk.php`.

JavaScript jest progressive enhancement: serwer zachowuje pełną walidację, a JS odpowiada za filtrowanie, zaznaczanie widocznych pozycji, stan `indeterminate`, presety i bieżący komunikat bezpieczeństwa.

## Weryfikacja

Workflow `verify-pr-leave-bulk` sprawdza:

1. poprawność patchy i Compose,
2. składnię PHP kontrolera i widoków,
3. render pickera i załadowanie CSS/JS,
4. pierwszy zapis 26 dni,
5. brak nadpisania 26 → 20 bez jawnej zgody,
6. skuteczne 26 → 20 po `overwrite_existing=1`.

Istniejący endpoint pojedynczy i testy FIFO/rollover pozostają bez zmian.
