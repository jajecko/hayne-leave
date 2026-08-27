# HAYNE Leave — System Overview

## Cel

HAYNE Leave jest wewnętrznym systemem obsługi nieobecności pracowników HAYNE. Bazuje na Jorani v1.0.4, ale posiada własną warstwę HAYNE obejmującą UI, polityki urlopowe, limity, workflow, integrację z Active Directory, kalendarz pracy, powiadomienia i PWA.

## Architektura

Runtime składa się z dwóch usług Docker Compose:

- `jorani` — Apache/PHP z przypiętym upstreamem Jorani oraz nakładanymi patchami i overlay HAYNE;
- `mysql` — MySQL z trwałym wolumenem `mysql_data`.

Repo nie vendoryzuje całego Jorani. `Dockerfile` pobiera przypiętą wersję upstream, a następnie nakłada `hayne/patches/` i `hayne/overlay/`. To jest ważna granica architektoniczna: modyfikacje upstream powinny być minimalne, jawne i fail-fast podczas builda.

## Główne obszary własne HAYNE

- `hayne/overlay/` — własne kontrolery, modele, widoki, assety, helpery i tłumaczenia;
- `hayne/patches/` — kontrolowane modyfikacje plików upstream;
- `hayne/sql/` — schemat i rozszerzenia danych HAYNE;
- `hayne/tools/` — narzędzia operacyjne/synchronizacyjne;
- `.github/workflows/` — automatyczne gate'y regresji i zmian;
- `docs/checkpoints/` — historyczny audyt trail implementacji.

## Główne domeny funkcjonalne

System obejmuje co najmniej:

- tworzenie, edycję, anulowanie i akceptację wniosków;
- typy nieobecności i ich polityki;
- roczne pule urlopowe i rozliczenie FIFO;
- urlop na żądanie;
- urlop opiekuńczy;
- siłę wyższą;
- opiekę nad dzieckiem art. 188;
- urlop okolicznościowy;
- odbiór dnia wolnego za święto;
- ręczne korekty wykorzystania;
- limity indywidualne i grupowe;
- kalendarz dni roboczych i świąt;
- role pracownik / manager / HR / administrator;
- uwierzytelnianie HAYNE AD przez LDAPS oraz konta lokalne break-glass;
- pocztę workflow;
- PWA i Web Push.

## Dane i trwałość

Dane biznesowe znajdują się w MySQL. Wolumen bazy jest elementem krytycznym i musi być objęty backupem niezależnym od repozytorium Git. Repozytorium przechowuje kod i migracje, nie produkcyjne dane ani sekrety.

## Zależności zewnętrzne

- upstream Jorani v1.0.4;
- HAYNE Active Directory przez LDAPS;
- Exchange Online SMTP relay;
- API kalendarza świąt (konfigurowalne przez `HAYNE_CALENDAR_API_URL_TEMPLATE`);
- przeglądarkowe API PWA/Web Push.

## Środowisko produkcyjne

Docelowa publikacja działa pod `https://urlopy.hayne.pl`. TLS/reverse proxy i firmowy DNS są częścią infrastruktury otaczającej aplikację i muszą być opisane operacyjnie w runbooku. Aplikacja nie powinna zakładać publicznego dostępu do backendu poza przyjętą architekturą sieciową HAYNE.

## Źródła prawdy

1. Kod na `main`.
2. Migracje/SQL i konfiguracja przykładowa zgodne z `main`.
3. Dokumenty kanoniczne w `docs/`.
4. Checkpointy — wyłącznie historia i dowody wcześniejszych zmian.
