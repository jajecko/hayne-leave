# PR-UI-06 — HAYNE leave balance target

## Cel

Usunąć ostatni wyraźnie legacy ekran z podstawowej ścieżki pracownika: `/leaves/counters`. Widok ma korzystać z tego samego języka wizualnego co zaakceptowany dashboard, `Nowy wniosek` i `Moje wnioski`.

## Kierunek UI

- topbar `Saldo urlopowe`,
- nagłówek i krótki opis po polsku,
- kontrolka `Stan na dzień` zachowująca istniejący datepicker i nawigację po dacie,
- jedna karta `Twoje limity`,
- nowoczesna tabela sald z polskimi etykietami,
- przycisk `Nowy wniosek`,
- czytelny empty state bez fikcyjnych danych,
- footer z linkiem do `Moje wnioski`.

## Guardrails

- bez zmian kontrolerów, modeli i bazy,
- bez zmian sposobu obliczania `$estimated` i `$simulated`,
- bez zmian źródła `$summary`, `$refDate`, `$isDefault`,
- zachować linki do filtrowanych wniosków dla wykorzystanych, zaplanowanych i oczekujących dni,
- zachować endpoint `leaves/counters/<ISO date>` oraz Bootstrap datepicker,
- brak fikcyjnych sald,
- brak widocznego brandingu upstream,
- realny screenshot CI musi zostać obejrzany przed merge.

## Implementacja

- pełny presentation override widoku w `hayne/overlay/legacy/application/views/leaves/counters.php`; logika obliczeń i datepicker pozostają zgodne z upstream,
- `hayne/overlay/assets/hayne/balance.css` jako izolowana warstwa UI,
- `010-header-branding.patch` ładuje `balance.css`,
- CI sprawdza marker `leave-balance-v1`, polskie copy, asset i runtime screenshot.
