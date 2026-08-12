# PR-LEAVE-FORM-02 — zakres dat i limit urlopu na żądanie

## Problem

Na `/leaves/create` po wybraniu daty rozpoczęcia i zakończenia modal `Proszę czekać` potrafił pozostać na ekranie bez końca, a liczba dni nie była wyliczana.

## Przyczyna

`request.js` przenosi stockowe selektory `#startdatetype` i `#enddatetype` do kontenera `.hayne-request-grid--dayparts`. Następnie `full-day.js`, zgodnie z polityką pełnych dni HAYNE, usuwał cały ten kontener z DOM. Stockowy `leave.edit-1.0.4.js` nadal wysyła te dwa pola do `/leaves/validate`; po ich usunięciu request nie zawierał wartości `Morning` / `Afternoon`, a endpoint mógł zakończyć się błędem przed zwróceniem JSON. Upstream JS chował modal wyłącznie w ścieżce sukcesu AJAX, więc błąd wyglądał jak nieskończone ładowanie.

## Zmiana

- `full-day.js` ukrywa wiersz części dnia, ale nie usuwa jego kontrolek z DOM;
- wartości pozostają wymuszone: start `Morning`, koniec `Afternoon`;
- request `/leaves/validate` ma timeout 15 s i przy błędzie modal jest zamykany oraz pojawia się komunikat zamiast nieskończonego spinnera;
- formularz on-demand dostaje dane `data-year`, `data-remaining`, `data-limit` z serwera;
- po zaznaczeniu `Urlop na żądanie` frontend respektuje liczbę pozostałych pełnych dni;
- jeśli wybrany zakres przekracza dostępny limit, koniec zakresu jest automatycznie skracany do maksymalnego możliwego zakresu w oparciu o wynik `/leaves/validate` i listę dni wolnych;
- użytkownik dostaje komunikat `Możesz wnioskować o maksymalnie X dni urlopu na żądanie. Zakres dat został automatycznie skrócony.`;
- backendowa walidacja 4 dni pozostaje bez zmian i nadal jest źródłem prawdy.

## Guardrails

- HAYNE nadal obsługuje wyłącznie pełne dni;
- brak zmian SQL;
- brak zmian FIFO i salda 20/26;
- brak osłabienia blokady 4 dni;
- dla zakresu w innym roku niż rok bieżącego `getFormState` frontend nie stosuje potencjalnie nieaktualnego limitu; backend pozostaje autorytatywny.

## Weryfikacja

Workflow `verify-pr-leave-date-range`:
- sprawdza składnię JS i brak usuwania day-part controls z DOM;
- buduje finalny obraz;
- wywołuje realny `/leaves/validate` dla pełnego 3-dniowego zakresu i wymaga HTTP 200 + `length=3`;
- tworzy 2-dniowy wniosek on-demand i wymaga, aby kolejny formularz wystawił `data-remaining="2"`, `data-limit="4"` oraz właściwy rok;
- odrzuca runtime z `Fatal error`, `Uncaught`, `Parse error`, `TypeError` lub `Undefined variable`.

## Produkcja

Po merge wystarczy rebuild kontenera aplikacji. Brak migracji bazy danych.
