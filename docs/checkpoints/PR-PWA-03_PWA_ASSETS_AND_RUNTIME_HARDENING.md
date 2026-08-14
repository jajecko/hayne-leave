# PR-PWA-03 — Ikony PWA i utwardzenie runtime Web Push

## Cel

Dokończyć produkcyjne wdrożenie PWA/Web Push po smoke testach na `https://urlopy.hayne.pl`: wdrożyć pełny zestaw ikon HAYNE Leave oraz utrwalić poprawki znalezione podczas rzeczywistego uruchomienia na QNAP.

## Zakres

- favicon `.ico` + PNG 16/32 px;
- Apple Touch Icon 180x180;
- ikony PWA 192x192 i 512x512;
- osobne ikony `maskable` 192x192 i 512x512;
- dedykowany monochromatyczny `notification-badge-128.png` dla Android/Chrome;
- service worker używa `pwa-icon-192.png` jako głównej ikony notyfikacji oraz osobnego badge;
- manifest wskazuje wyłącznie produkcyjne PNG i rozdziela `any` od `maskable`;
- header deklaruje favicony i Apple Touch Icon;
- własny prompt HAYNE znika przed wywołaniem natywnego `Notification.requestPermission()`, więc użytkownik nie widzi dwóch promptów jednocześnie;
- PHP `bcmath` jest instalowane w obrazie, aby biblioteka Web Push nie emitowała notice i nie psuła redirectu Jorani po złożeniu wniosku;
- rootowy `.htaccess` dopuszcza wyłącznie `service-worker.js` i `manifest.webmanifest`, pozostawiając ochronę pozostałych plików root;
- CI sprawdza bcmath, bundle ikon, obecność assetów runtime oraz HTTP 200 dla service workera i manifestu.

## Źródło ikon

Pakiet użytkownika `HAYNE_Leave_transparent_icons.zip` został przejrzany. Do runtime trafia minimalny komplet produkcyjny. Badge powiadomień został utworzony z czarnych linii grafiki na przezroczystym tle, bez białych wypełnień, aby Android mógł poprawnie potraktować go jako maskę monochromatyczną.

Bundle repozytorium: `hayne/assets/hayne-leave-icons.tar.gz`.

SHA256 bundle użytego podczas przygotowania: `3fafa2bd861564ade5a73096b047adb4620d25aeaf2e81d31a3234a02881419b`.

## Potwierdzone problemy z produkcyjnego smoke testu

1. `service-worker.js` i `manifest.webmanifest` zwracały 403 przez ochronną regułę root `.htaccess` Jorani v1.0.4.
2. Web Push emitował notice o braku GMP/BCMath; output powodował następnie `headers already sent` podczas redirectu po wniosku.
3. Własny prompt HAYNE pozostawał widoczny równocześnie z natywnym promptem przeglądarki.
4. Stara ikona `favicon.svg` była używana jednocześnie jako `icon` i `badge`, co nie dawało właściwego badge powiadomień na Androidzie.

## Potwierdzone smoke testy przed utrwaleniem

- `HAYNE_PUSH_ENABLED=TRUE` i stabilny VAPID w runtime;
- subskrypcje Chrome Windows i Chrome Android zapisane w `hayne_push_subscriptions`;
- wysyłka workflow zapisała `last_success_at`, `failure_count=0`;
- prawdziwy push managera pojawił się na Androidzie z treścią „Masz nowy wniosek wymagający uwagi.”;
- lokalne `showNotification()` działa na Windows po włączeniu systemowych powiadomień Chrome i wyłączeniu trybu „Nie przeszkadzać”.

## Bez zmian

- brak zmian w logice urlopów, akceptacji, FIFO i limitów;
- brak zmian w AD;
- brak zmian SMTP;
- brak offline cache i handlera `fetch` w service workerze;
- brak zmiany VAPID; istniejące subskrypcje pozostają ważne.
