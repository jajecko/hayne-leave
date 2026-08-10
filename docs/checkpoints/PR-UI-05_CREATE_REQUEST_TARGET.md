# PR-UI-05 — HAYNE create request target

## Cel

Doprowadzić ekran `Nowy wniosek` do zaakceptowanej makiety HAYNE: ten sam authenticated shell co dashboard, nagłówek `Nowy wniosek`, pełnoszeroka karta formularza, dwukolumnowe daty i części dnia, widoczne saldo, neutralne pole liczby dni oraz trzy docelowe akcje.

## Acceptance target

Referencją wizualną jest makieta `panel_wniosku_urlopowego_hayne.png` przekazana/zaakceptowana w bieżącym workstreamie. Nie traktujemy jej jako luźnej inspiracji — realny screenshot CI `/leaves/create` ma być porównany z tą kompozycją przed merge.

## Guardrails

- bez zmian kontrolerów, modeli i bazy danych,
- bez zmian endpointu `leaves/create`,
- zachować `#frmLeaveForm`, nazwy i ID pól oraz istniejący CSRF,
- zachować wartości `Morning` / `Afternoon` w selectach; lokalizujemy tylko tekst widoczny,
- zachować submit semantics: `status=2` wysyła wniosek, `status=1` zapisuje plan,
- zachować istniejący Jorani datepicker, walidację, obliczanie duration i overlap checks,
- nie fałszować salda ani liczby dni,
- realne screenshoty CI muszą zostać obejrzane przed merge.

## Zakres

- `request.js`: bezpieczny enhancer DOM bazujący na istniejących ID/nazwach pól; układa formularz zgodnie z targetem bez zmiany kontraktu backendowego,
- `request.css`: targetowy layout, kontrolki, grid dat/części dnia, karta i przyciski,
- `010-header-branding.patch`: ładowanie `request.js`,
- `.github/workflows/verify.yml`: kontrola assetu i markerów targetu,
- brak zmian w leave/approval workflow.

## Weryfikacja

PR nie może zostać zmergowany wyłącznie na podstawie zielonego CI. Ostateczna decyzja wymaga obejrzenia `hayne-create-leave.png` oraz szybkiego regression smoke `home`, `leaves`, `balance`, `login`.
