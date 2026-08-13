# PR-PWA-02 — Web Push dla HAYNE Leave

## Cel

Dodać bezpieczne powiadomienia Web Push jako kanał uzupełniający istniejące e-maile Jorani, bez zmiany logiki urlopowej i bez trybu offline.

## Zakres

- biblioteka `minishlink/web-push` v11.0.0 oraz Guzzle instalowane w obrazie;
- wymagane rozszerzenia PHP `curl` i `mbstring`;
- VAPID wyłącznie z deployment `.env`;
- idempotentny instalator tabeli `hayne_push_subscriptions`;
- generator stabilnej pary VAPID do jednorazowego użycia przez operatora;
- zalogowane, CSRF-protected endpointy `push/settings`, `push/subscribe`, `push/unsubscribe`;
- subskrypcje per urządzenie, przypisane do użytkownika Jorani;
- dyskretny własny prompt „Włącz powiadomienia”; natywne pytanie przeglądarki pojawia się dopiero po kliknięciu użytkownika;
- obsługa `push` i `notificationclick` w service workerze;
- most z istniejących maili `Leaves` / `Requests` do push, dzięki czemu push nie duplikuje logiki workflow;
- manager i delegaci otrzymują push przy wiadomościach z `Leaves`, a pracownik przy decyzjach z `Requests`;
- wygasłe subskrypcje są automatycznie usuwane, błędy push nie mogą przerwać maila ani operacji urlopowej.

## Prywatność i bezpieczeństwo

Treść na ekranie blokady jest celowo ogólna. Push nie zawiera przyczyny urlopu, dat, salda ani innych danych wrażliwych. Kliknięcie prowadzi do zalogowanej aplikacji.

Klucz prywatny VAPID nie jest zwracany przez endpoint i nie może trafić do repozytorium. `HAYNE_PUSH_ENABLED` domyślnie pozostaje `FALSE`.

## Offline

Service worker nadal nie ma handlera `fetch` i nie używa Cache API. Wnioski oraz decyzje zawsze korzystają z aktualnego stanu serwera.

## Wdrożenie QNAP

Po ręcznym wgraniu plików i przebudowaniu obrazu operator uruchamia raz:

`docker compose -f compose.yaml exec -T app php /opt/hayne/push-install.php`

Następnie generuje VAPID:

`docker compose -f compose.yaml exec -T app php /opt/hayne/push-vapid.php`

Wygenerowane wartości należy wpisać do deployment `.env`, ustawić `HAYNE_PUSH_ENABLED=TRUE`, a następnie wykonać recreate kontenera aplikacji. Para VAPID musi pozostać stała; jej zmiana unieważnia istniejące subskrypcje.

## Poza zakresem

- centrum powiadomień / historia in-app;
- badge z liczbą nieprzeczytanych;
- offline cache;
- zmiany SMTP i szablonów mailowych;
- zmiany AD;
- zmiany reguł akceptacji, limitów i naliczania urlopów.
