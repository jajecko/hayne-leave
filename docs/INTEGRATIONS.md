# HAYNE Leave — Integrations

## Active Directory / LDAPS

Konfiguracja znajduje się w zmiennych `HAYNE_AD_*`.

Role integracji:

- preview katalogu;
- plan synchronizacji;
- kontrolowany apply użytkowników;
- interaktywne uwierzytelnianie zsynchronizowanych użytkowników.

Hosty są konfigurowalne i mogą obejmować więcej niż jeden kontroler domeny. Połączenie korzysta z portu 636 oraz zaufanego CA montowanego do `/opt/hayne/certs`.

Bezpieczny rollout: preview -> plan -> ocena liczby zmian -> apply z limitem -> walidacja -> dopiero później ewentualne włączenie logowania AD.

## Exchange Online SMTP relay

Domyślna konfiguracja przykładowa używa `hayne-pl.mail.protection.outlook.com`, port 25 i TLS. Wdrożenie opiera się na firmowym mechanizmie relay/connector; aplikacja nie powinna wymagać wpisania hasła, jeżeli relay autoryzuje źródło po stronie infrastruktury.

Nadawca: `urlopy@hayne.pl`, nazwa `HAYNE Leave`.

Test integracji powinien obejmować faktyczne dostarczenie do Outlooka oraz poprawny rendering logo i najważniejszych danych workflow.

## Managed work calendar

Narzędzie `hayne/tools/calendar-sync.php` utrzymuje weekendy i polskie święta. Źródło API jest konfigurowalne przez `HAYNE_CALENDAR_API_URL_TEMPLATE`; aktualny przykład wskazuje Nager.Date.

Synchronizacja jest domyślnie read-only. Zapis wymaga osobnego gate'a `HAYNE_CALENDAR_APPLY_ENABLED=TRUE` oraz jawnej operacji apply.

Awaria API nie może usuwać istniejącego kalendarza ani powodować masowych zmian bez planu.

## PWA

Root zawiera `manifest.webmanifest` i `service-worker.js`. Assety ikon i kod PWA znajdują się w overlay HAYNE. Zmiana PWA wymaga testu instalowalności, cache/update oraz działania na urządzeniu mobilnym.

## Web Push

Konfiguracja:

- `HAYNE_PUSH_ENABLED`;
- `VAPID_SUBJECT`;
- `VAPID_PUBLIC_KEY`;
- `VAPID_PRIVATE_KEY`;
- `HAYNE_PUSH_TTL`.

Klucze generuje narzędzie `hayne/tools/push-vapid.php`. Prywatny klucz jest sekretem deploymentu. `push-install.php` wspiera instalację wymaganych elementów runtime.

Push jest kanałem dodatkowym. Brak push nie może zmieniać stanu workflow ani powodować ponownego wykonania operacji biznesowej.

## Reverse proxy / DNS / TLS

Warstwa infrastrukturalna publikuje aplikację jako `https://urlopy.hayne.pl`. Backend aplikacji może działać po HTTP w sieci wewnętrznej, a terminacja TLS odbywa się na Apache/reverse proxy z firmowym certyfikatem wildcard.

Wewnętrzny DNS musi kierować nazwę na właściwy host reverse proxy. Różnice LAN/VPN/Wi-Fi diagnozuje się warstwowo, nie przez zmianę `BASE_URL` bez potwierdzenia przyczyny.

## Zasada integracji

Każda nowa integracja musi posiadać:

- właściciela i cel;
- konfigurację przez env zamiast hardcode sekretów;
- timeout;
- retry tylko dla operacji bezpiecznych/idempotentnych;
- tryb awarii/fallback;
- logowanie bez sekretów;
- test integracyjny lub kontrolowany probe;
- opis operacyjny i procedurę wyłączenia.
