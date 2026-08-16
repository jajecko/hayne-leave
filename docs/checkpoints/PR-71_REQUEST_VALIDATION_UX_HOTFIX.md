# PR-71 — request validation UX hotfix

## Problem

Na `/leaves/create` występowały trzy powiązane problemy:

1. po wybraniu `Urlop opiekuńczy` backend wymagał imienia i nazwiska osoby wymagającej opieki, ale panel z tym polem mógł pozostać niewidoczny;
2. po błędzie backendowym powrót na formularz zerował wcześniej wprowadzone dane i przywracał domyślny rodzaj nieobecności;
3. datepicker potrafił zachowywać stare ograniczenia dat po wcześniejszych stanach formularza / starszym runtime, przez co dostępny zakres wyglądał niespójnie.

## Przyczyna

### Pola urlopu opiekuńczego

HAYNE-owe panele polityk nasłuchiwały natywnego `change` na `#type`, podczas gdy pole rodzaju nieobecności jest obsługiwane przez Select2/jQuery. W efekcie wartość `#type` mogła się zmienić, ale panel opiekuńczy nadal pozostawał `display:none`.

### Reset formularza

Część backendowych walidacji HAYNE wraca do `/leaves/create` przez redirect. Redirect tworzy nowy request i stockowy formularz Jorani ponownie ustawia wartości domyślne.

### Daty

HAYNE nie ma już biznesowej blokady do bieżącego roku. Datepicker powinien mieć wyłącznie relację `koniec >= początek` oraz — tylko po świadomym zaznaczeniu urlopu na żądanie — jego istniejący limit końca zakresu. Stare/sticky opcje `minDate` / `maxDate` nie powinny przeżyć zmiany stanu formularza.

## Zmiana

Dodano izolowany frontend hotfix ładowany pod nową nazwą assetu (cache-busting):

- `assets/hayne/request-form-hotfix.js?v=1`
- `assets/hayne/request-form-hotfix.css?v=1`

Hotfix:

- synchronizuje widoczność wszystkich HAYNE-owych paneli polityk z aktualną wartością Select2 `#type`;
- dla `Urlop opiekuńczy` pokazuje i poprawnie oznacza jako wymagane: osobę, relację, przyczynę oraz warunkowo adres wspólnego gospodarstwa;
- analogicznie stabilizuje panele siły wyższej, opieki nad dzieckiem, urlopu okolicznościowego i dnia wolnego za święto;
- przechwytuje brakujące wymagane pola przed wysłaniem formularza;
- podświetla wyłącznie błędne pole, pokazuje pod nim konkretny komunikat i przenosi fokus do tego pola;
- przed rzeczywistym POST zapisuje roboczy stan formularza do `sessionStorage` na maksymalnie 30 minut;
- po backendowym redirect z błędem odtwarza rodzaj nieobecności, daty, liczbę dni, komentarz i pola polityk;
- po wyjściu z formularza czyści roboczy draft, więc poprawnie złożony wniosek nie pojawia się ponownie przy następnym wejściu;
- normalizuje datepicker po inicjalizacji i zmianach: brak HAYNE-only ograniczenia roku, `end.minDate = start`, `start.maxDate = end`, a `end.maxDate` istnieje tylko gdy aktywny jest limit urlopu na żądanie;
- ustawia standardowy zakres selektora lat jQuery UI `c-10:c+10`, bez blokowania dat do bieżącego roku.

## Bez zmian

- brak zmian w backendowych limitach i źródłach prawdy;
- brak zmian w FIFO / saldach 20/26;
- brak zmian w regule 5 dni urlopu opiekuńczego;
- brak zmian w limicie 4 dni urlopu na żądanie;
- brak zmian w mailach, push, AD, bazie danych i workflow akceptacji;
- nie przywracamy ograniczenia wniosków wyłącznie do bieżącego roku.

## Smoke po wdrożeniu

1. Otwórz `Nowy wniosek`, wybierz `Urlop opiekuńczy` — panel z danymi osoby ma pojawić się natychmiast.
2. Bez wpisywania osoby kliknij `Wyślij wniosek` — POST nie powinien nastąpić; pole osoby ma dostać czerwony stan i komunikat bez kasowania pozostałych danych.
3. Uzupełnij osobę, zostaw pustą relację — błąd ma przejść tylko na relację.
4. Uzupełnij wszystkie dane i sprowokuj backendowy błąd polityki — po powrocie na `/leaves/create` wcześniej wpisane wartości mają zostać odtworzone.
5. Przełącz kilka razy rodzaj nieobecności — właściwy panel ma zawsze odpowiadać aktualnemu typowi.
6. Sprawdź daty na przełomie roku, np. grudzień -> styczeń — brak blokady do bieżącego roku.
7. Sprawdź `Urlop na żądanie` — jego istniejący cap końca zakresu nadal działa tylko po zaznaczeniu opcji.
8. Wyślij poprawny wniosek, wróć później do `Nowy wniosek` — stary draft nie może się odtworzyć.

## CI

Workflow `verify-pr-request-form-hotfix`:

- sprawdza składnię nowego JS;
- sprawdza obecność guardów caregiver/draft/date picker;
- buduje finalny obraz HAYNE Leave przeciwko Jorani v1.0.4;
- weryfikuje, że finalny `header.php` ładuje nowe assety;
- lintuje finalny header, create view i caregiver view.
