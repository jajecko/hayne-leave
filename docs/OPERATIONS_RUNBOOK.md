# HAYNE Leave — Operations Runbook

## Zasada nadrzędna

Nie wykonuj zmian produkcyjnych bez kopii bazy i możliwości powrotu do poprzedniego obrazu/commita. Sekretów produkcyjnych nie zapisujemy w Git.

## Standardowa lokalizacja QNAP

W dotychczasowym wdrożeniu aplikacja jest utrzymywana przez Docker Compose na QNAP. Przed operacją zawsze ustal faktyczną lokalizację aktywnego `compose.yaml`/`docker-compose.yml`; nie zakładaj ślepo ścieżki z historycznych sesji.

## Kontrola stanu

```sh
docker compose ps
docker ps --format '{{.Names}}\t{{.Status}}\t{{.Ports}}'
```

Sprawdź osobno aplikację i MySQL. Baza powinna przejść healthcheck przed uznaniem systemu za gotowy.

## Start / recreate

```sh
docker compose up -d
```

Po zmianie obrazu lub overlay:

```sh
docker compose up -d --build
```

Jeżeli zmieniana jest tylko aplikacja i baza ma pozostać nietknięta, preferuj kontrolowany recreate usługi aplikacyjnej zamiast usuwania całego stacku.

## Weryfikacja po wdrożeniu

Minimalny smoke test:

1. kontenery są `Up` i MySQL jest healthy;
2. HTTP backend odpowiada;
3. `https://urlopy.hayne.pl` otwiera ekran logowania;
4. logowanie kontem testowym działa;
5. dashboard działa;
6. utworzenie formularza wniosku działa;
7. lista własnych wniosków działa;
8. manager/HR widzi właściwe powierzchnie;
9. brak nowych błędów PHP/Apache w logach;
10. jeśli zmiana dotyczy poczty/push/AD — wykonaj osobny smoke test tej integracji.

## Logi

Konfiguracja domyślna kieruje log aplikacji na stdout (`LOG_PATH=php://stdout`). Podstawowa diagnostyka:

```sh
docker compose logs --tail=200 jorani
docker compose logs --tail=200 mysql
```

Dla problemu występującego w czasie rzeczywistym użyj `-f` i odtwórz dokładnie jedną operację.

## Backup bazy

Backup logiczny należy wykonać przed migracją, większym deployem i operacją administracyjną wpływającą na dane.

Przykładowy wzorzec (nazwy kontenera i poświadczenia ustal z aktywnego deploymentu):

```sh
docker exec <mysql-container> sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction --routines --triggers "$MYSQL_DATABASE"' > hayne-leave-$(date +%Y%m%d-%H%M%S).sql
```

Po backupie sprawdź, że plik istnieje, ma sensowny rozmiar i zawiera nagłówek SQL. Backup powinien zostać skopiowany poza wolumen/host, którego awaria ma być pokryta.

## Restore bazy

Restore jest operacją destrukcyjną. Najpierw zatrzymaj ruch do aplikacji i wykonaj kopię aktualnego stanu, nawet jeżeli jest uszkodzony.

Ogólny wzorzec:

```sh
cat backup.sql | docker exec -i <mysql-container> sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

Następnie uruchom smoke test oraz kontrolę liczby/ciągłości kluczowych rekordów.

## Aktualizacja kodu

Preferowany model:

1. merge sprawdzonego PR do `main`;
2. backup DB;
3. `git fetch` / `git pull --ff-only` w deployment checkout;
4. build/recreate aplikacji;
5. migracje tylko wtedy, gdy są wymagane przez release;
6. smoke test;
7. obserwacja logów;
8. dopiero wtedy zamknięcie okna zmian.

Nigdy nie rób ręcznych poprawek produkcyjnych w plikach kontenera jako trwałego rozwiązania. Każda trwała poprawka musi wrócić do repo.

## Rollback

Rollback kodu:

- wróć checkoutem do ostatniego znanego dobrego SHA;
- przebuduj/recreate aplikację;
- jeżeli release zmienił schemat/dane w sposób nieodwracalny, wykonaj zatwierdzoną procedurę restore.

Rollback nie może polegać wyłącznie na cofnięciu plików, jeśli nowa wersja zdążyła zmienić dane.

## Diagnostyka sieci / HTTPS

Warstwy należy sprawdzać osobno:

1. aplikacja HTTP na porcie hosta;
2. reverse proxy Apache;
3. certyfikat wildcard TLS;
4. DNS wewnętrzny;
5. routing LAN/VPN;
6. cache DNS urządzenia/przeglądarki.

Jeżeli backend działa po IP/porcie, a domena nie działa, nie debuguj PHP jako pierwszego podejrzanego.

## AD / LDAPS

Przed włączeniem write/apply najpierw używaj trybu preview/planner. `HAYNE_AD_SYNC_ENABLED` i `HAYNE_AD_APPLY_ENABLED` są oddzielnymi gate'ami. Konto `jadmin` (lub aktualnie skonfigurowane `HAYNE_AD_AUTH_LOCAL_LOGINS`) pełni rolę lokalnego break-glass i nie może zostać przypadkowo uzależnione od AD.

## Kalendarz

Synchronizacja kalendarza ma osobny gate `HAYNE_CALENDAR_APPLY_ENABLED`. Najpierw wykonuj odczyt/plan, dopiero potem apply po sprawdzeniu zakresu zmian.

## Web Push

Prywatny klucz VAPID istnieje wyłącznie w deployment `.env`. Utrata klucza wymaga rotacji i może wymusić ponowną subskrypcję klientów. Nie commituj go.

## Kryterium zakończenia incydentu

Incydent jest zamknięty dopiero gdy:

- przywrócono funkcję;
- potwierdzono integralność danych;
- ustalono przyczynę;
- trwała poprawka znajduje się w repo;
- dokumentacja/runbook został uzupełniony, jeżeli brak instrukcji utrudnił diagnozę.
