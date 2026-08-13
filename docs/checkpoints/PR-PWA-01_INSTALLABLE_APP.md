# PR-PWA-01 — instalowalny HAYNE Leave

## Cel

Dodać minimalną, bezpieczną warstwę PWA, aby HAYNE Leave mógł być instalowany jak aplikacja na wspieranych urządzeniach, bez zmiany logiki urlopowej.

## Zakres

- `manifest.webmanifest` z nazwą HAYNE Leave, trybem `standalone` i wspólną ikoną HAYNE;
- `service-worker.js` rejestrowany z poziomu wspólnego nagłówka;
- `hayne/overlay/assets/hayne/pwa.js` do bezpiecznej rejestracji service workera;
- alias wspólnej ikony jako wariant `maskable`;
- meta tagi PWA / Apple Web App w `legacy/application/views/templates/header.php` przez patch `270-pwa-installability.patch`.

## Guardraile

Service worker celowo nie ma handlera `fetch` i nie używa Cache API. HAYNE Leave nie udaje trybu offline: formularze, decyzje i stan urlopów zawsze mają korzystać z aktualnego stanu serwera.

Rejestracja service workera jest wykonywana tylko w bezpiecznym kontekście HTTPS albo lokalnie na `localhost` / `127.0.0.1`.

## Poza zakresem

- Web Push i VAPID;
- uprawnienia do powiadomień;
- przechowywanie subskrypcji urządzeń;
- centrum powiadomień;
- zmiany SMTP i szablonów mailowych;
- offline cache;
- zmiany bazy danych;
- zmiany workflow składania, zatwierdzania lub odrzucania wniosków.

## Weryfikacja lokalna

- manifest przechodzi `python3 -m json.tool`;
- `service-worker.js` oraz `pwa.js` przechodzą `node --check`;
- patch `270-pwa-installability.patch` ma minimalny punkt zaczepienia na końcowym `</head>` i jest celowo ostatnim patchem nagłówka w obecnym zestawie;
- service worker nie zawiera `fetch` ani wywołań `caches.*`.

## Następny izolowany slice

Po wdrożeniu PWA-01 można osobno dodać Web Push: VAPID, zgodę użytkownika, subskrypcje per urządzenie oraz zdarzenia push dla wniosków i decyzji.
