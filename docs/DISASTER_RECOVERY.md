# HAYNE Leave — Disaster Recovery

## Cel

Ta procedura ma umożliwić odtworzenie HAYNE Leave bez wiedzy ukrytej w historii czatu lub pamięci pojedynczej osoby.

## Co musi istnieć poza uszkodzonym hostem

- dostęp do repo `jajecko/hayne-leave`;
- znany dobry SHA/release;
- aktualny dump MySQL;
- bezpieczna kopia produkcyjnego `.env` lub możliwość odtworzenia wszystkich sekretów;
- CA wymagane do LDAPS;
- konfiguracja reverse proxy/DNS/TLS lub dostęp do administratora tej infrastruktury;
- prywatny klucz VAPID, jeśli zachowanie istniejących subskrypcji push ma zostać zachowane.

## Odtworzenie aplikacji

1. przygotuj host z Docker Engine i Docker Compose;
2. sklonuj repo;
3. checkout znanego dobrego SHA;
4. odtwórz `.env` z bezpiecznego źródła;
5. odtwórz katalog certyfikatów CA;
6. zbuduj i uruchom stack;
7. poczekaj na healthy MySQL;
8. zatrzymaj ruch użytkowników przed importem produkcyjnej bazy;
9. zaimportuj dump;
10. uruchom aplikację i smoke test.

## Odtworzenie infrastruktury wejściowej

Potwierdź kolejno:

- backend odpowiada po lokalnym IP/porcie;
- reverse proxy wskazuje na właściwy backend;
- HTTP przekierowuje na HTTPS zgodnie z polityką;
- certyfikat wildcard jest poprawny;
- DNS `urlopy.hayne.pl` wskazuje na właściwy host wewnętrzny;
- dostęp działa z LAN i VPN zgodnie z wymaganiami.

## Walidacja danych po restore

Nie wystarcza ekran logowania. Sprawdź co najmniej:

- liczbę użytkowników/pracowników;
- aktywne kontrakty;
- typy nieobecności;
- najnowsze wnioski i ich statusy;
- salda/pule dla kilku znanych pracowników;
- kalendarz dni niepracujących;
- role administrator/HR/manager;
- możliwość utworzenia testowego wniosku bez naruszenia danych produkcyjnych.

## Integracje po restore

Włączaj je etapami:

1. SMTP — test maila;
2. AD preview — bez apply;
3. AD login — test konta zwykłego i break-glass;
4. calendar plan — bez apply;
5. Web Push — test jednej kontrolowanej subskrypcji.

## RPO/RTO

Repo samo nie zapewnia RPO dla danych. Organizacja powinna jawnie ustalić:

- maksymalną akceptowalną utratę danych (RPO);
- maksymalny czas niedostępności (RTO);
- częstotliwość automatycznych backupów;
- retencję;
- lokalizację kopii off-host/off-site;
- cykliczny test restore.

Do czasu formalnego ustalenia tych parametrów DR nie może być uznane za w pełni zweryfikowane operacyjnie.

## Test DR

Co najmniej okresowo wykonaj próbne odtworzenie na izolowanym środowisku. Sukces oznacza nie tylko import DB, ale przejście pełnego smoke testu oraz udokumentowanie czasu odtworzenia i brakujących zależności.
