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
- manifest wskazuje produkcyjne PNG i rozdziela `any` od `maskable`;
- header deklaruje favicony i Apple Touch Icon;
- własny prompt HAYNE znika przed wywołaniem natywnego `Notification.requestPermission()`, więc użytkownik nie widzi dwóch promptów jednocześnie;
- PHP `bcmath` jest instalowane w obrazie, aby biblioteka Web Push nie emitowała notice i nie psuła redirectu Jorani po złożeniu wniosku;
- rootowy `.htaccess` dopuszcza wyłącznie `service-worker.js` i `manifest.webmanifest`, pozostawiając ochronę pozostałych plików root;
- CI sprawdza sygnatury i wymiary ikon, `bcmath`, obecność assetów runtime oraz HTTP 200 dla kluczowych zasobów PWA.

## Źródło ikon i sposób przechowywania

Pakiet użytkownika HAYNE Leave został przejrzany. Repozytorium przechowuje bezpośrednio docelowe assety runtime w `hayne/overlay/assets/hayne/`; nie ma archiwum, base64 ani logiki rozpakowywania ikon podczas budowania obrazu.

Minimalny zestaw runtime:

- `favicon.ico`;
- `favicon-16x16.png`;
- `favicon-32x32.png`;
- `apple-touch-icon.png`;
- `pwa-icon-192.png`;
- `pwa-icon-512.png`;
- `pwa-icon-maskable-192.png`;
- `pwa-icon-maskable-512.png`;
- `notification-badge-128.png`.

Badge powiadomień jest dedykowaną wersją monochromatyczną na przezroczystym tle, aby Android/Chrome mógł poprawnie użyć go jako małej maski powiadomienia.

## Potwierdzone problemy z produkcyjnego smoke testu

1. `service-worker.js` i `manifest.webmanifest` zwracały 403 przez ochronną regułę root `.htaccess` Jorani v1.0.4.
2. Web Push emitował notice o braku GMP/BCMath; output powodował następnie `headers already sent` podczas redirectu po wniosku.
3. Własny prompt HAYNE pozostawał widoczny równocześnie z natywnym promptem przeglądarki.
4. Stara ikona `favicon.svg` była używana jednocześnie jako `icon` i `badge`, co nie dawało właściwego badge powiadomień na Androidzie.
5. Próba przenoszenia ikon jako zakodowanego bundle została odrzucona po smoke teście; finalny kontrakt repo używa zwykłych plików binarnych w overlayu.

## Potwierdzone smoke testy produkcyjne

- `HAYNE_PUSH_ENABLED=TRUE` i stabilny VAPID w runtime;
- subskrypcje Chrome Windows i Chrome Android zapisane w `hayne_push_subscriptions`;
- wysyłka workflow zapisała `last_success_at`, `failure_count=0`;
- prawdziwy push managera pojawił się na Androidzie;
- lokalne `showNotification()` działa na Windows po włączeniu systemowych powiadomień Chrome i wyłączeniu trybu „Nie przeszkadzać”;
- obraz z `Dockerfile.hayne-local` zbudował się poprawnie po ręcznym umieszczeniu ikon w overlayu;
- wszystkie 9 assetów ikon istnieje w `/var/www/html/assets/hayne/`;
- favicon, Apple Touch Icon, ikony PWA/maskable, badge, manifest i service worker odpowiadają HTTP 200;
- runtime service worker wskazuje `pwa-icon-192.png` jako `icon` i `notification-badge-128.png` jako `badge`;
- runtime header wskazuje manifest, favicon `.ico`, favicon PNG 16/32 i Apple Touch Icon;
- PHP runtime zawiera `bcmath`.

## Do finalnego visual smoke przed merge

Po wdrożeniu assetów bezpośrednio z tego PR należy jeszcze potwierdzić wizualnie:

- favicon w Chrome po odświeżeniu cache;
- ikonę świeżo zainstalowanej PWA;
- dedykowany monochromatyczny badge w prawdziwym powiadomieniu Android/Chrome.

## Bez zmian

- brak zmian w logice urlopów, akceptacji, FIFO i limitów;
- brak zmian w AD;
- brak zmian SMTP;
- brak offline cache i handlera `fetch` w service workerze;
- brak zmiany VAPID; istniejące subskrypcje pozostają ważne.
