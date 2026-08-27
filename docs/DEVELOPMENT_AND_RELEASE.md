# HAYNE Leave — Development and Release

## Zasada pracy

`main` ma reprezentować wdrażalny stan systemu. Zmiany wykonujemy na krótkich branchach i wprowadzamy przez PR z automatycznymi gate'ami.

## Architektura zmian

Preferowana kolejność:

1. wykorzystaj overlay HAYNE, jeśli można rozszerzyć system bez zmiany upstream;
2. jeżeli trzeba zmienić plik Jorani — dodaj minimalny patch w `hayne/patches/`;
3. dane/schemat — jawny, idempotentny lub kontrolowany SQL/migration artifact;
4. dodaj regresję CI dla istotnego zachowania;
5. zaktualizuj dokument kanoniczny;
6. checkpoint może zostać dodany jako dowód, ale nie zastępuje dokumentacji systemu.

## Lokalny start

```sh
cp .env.example .env
docker compose up --build
```

Nigdy nie commituj lokalnego `.env`.

## Testowanie

Każdy slice powinien posiadać testy adekwatne do ryzyka. W repo istnieje centralny workflow `verify.yml` oraz wyspecjalizowane workflow regresyjne. Nowa funkcja nie powinna opierać się wyłącznie na ręcznym klikaniu.

Minimalny zestaw dla zmiany funkcjonalnej:

- build obrazu;
- test/regresja backendu lub statyczny guard właściwy dla zmiany;
- test uprawnień;
- test happy path;
- test co najmniej jednego błędu/edge case;
- smoke test UI, jeśli zmiana dotyka interakcji użytkownika.

## Zmiany UI

UI musi być sprawdzane desktop + mobile. Elementy interaktywne menu, drawerów, formularzy i modali wymagają testu faktycznego kliknięcia/nawigacji, nie tylko obecności selektora w HTML/CSS.

## Zmiany danych

PR zmieniający model danych musi opisać:

- schemat przed/po;
- czy migracja jest idempotentna;
- wpływ na istniejące dane;
- rollback/restore;
- kolejność deploy code vs migration;
- test na kopii danych lub kontrolowanym fixture.

## Zmiany integracji

Dla AD, SMTP, kalendarza i Web Push wymagane są:

- timeouty;
- bezpieczne zachowanie przy niedostępności usługi;
- brak sekretów w logach;
- read-only preview, jeśli integracja może wykonywać masowe zapisy;
- limit blast radius (`MAX_*`), jeśli operacja masowa.

## PR Definition of Done

PR jest gotowy do merge, gdy:

- zakres jest mały i jednoznaczny;
- CI jest zielone;
- nie ma sekretów;
- uprawnienia zostały sprawdzone;
- dane/migracje mają plan rollbacku;
- dokumentacja kanoniczna jest aktualna;
- checkpoint, jeżeli istnieje, opisuje faktycznie wykonany stan, nie plan;
- reviewer może odtworzyć test bez wiedzy z czatu.

## Release

Release produkcyjny powinien wskazywać konkretny SHA `main`. Procedura produkcyjna znajduje się w `OPERATIONS_RUNBOOK.md`.

## Hotfix

Hotfix nadal musi wrócić do Git i przejść minimalne testy regresji. Ręczna zmiana w działającym kontenerze jest dopuszczalna wyłącznie jako krótkotrwała akcja ratunkowa i musi zostać niezwłocznie zastąpiona reprodukowalnym commitem/releasem.
