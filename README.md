# HAYNE Leave

Firmowy system obsługi nieobecności HAYNE oparty na przypiętym upstreamie **Jorani v1.0.4** i rozszerzony o warstwę HAYNE.

> Pełna dokumentacja systemowa i operacyjna: **[docs/README.md](docs/README.md)**.

## Architektura

Repozytorium nie kopiuje całego upstreamu Jorani. Obraz aplikacji pobiera przypięte wydanie, instaluje zależności, a następnie nakłada warstwę HAYNE:

- `hayne/overlay/` — własne kontrolery, modele, widoki, helpery, assety i tłumaczenia;
- `hayne/patches/` — minimalne kontrolowane zmiany upstream;
- `hayne/sql/` — rozszerzenia modelu danych HAYNE;
- `hayne/tools/` — narzędzia AD, kalendarza i Web Push;
- `.github/workflows/` — gate'y CI/regresji;
- `docs/` — dokumentacja kanoniczna;
- `docs/checkpoints/` — historyczny audit trail zmian.

Jeśli patch przestanie pasować do upstreamu, build/CI powinien zakończyć się błędem zamiast po cichu pominąć zmianę.

## Zakres HAYNE

Projekt nie jest już wyłącznie rebrandingiem Jorani. Obejmuje m.in. własne polityki nieobecności, pule i FIFO, limity, workflow, powierzchnie HR/manager, kalendarz pracy, AD/LDAPS, pocztę workflow, PWA i Web Push.

## Uruchomienie lokalne

Wymagany Docker z Docker Compose.

```bash
cp .env.example .env
docker compose up --build
```

Domyślnie aplikacja jest dostępna pod `http://localhost:8080`.

Przed użyciem poza local należy ustawić bezpieczne wartości co najmniej dla haseł MySQL, `ENC_KEY`, `BASE_URL`, SMTP oraz — jeśli używane — AD/LDAPS i VAPID. Sekretów nie commitujemy.

## Produkcja i operacje

Nie wdrażaj projektu na podstawie samego README. Procedury deploy, backup/restore, rollback, HTTPS/DNS, AD i DR znajdują się w [docs/OPERATIONS_RUNBOOK.md](docs/OPERATIONS_RUNBOOK.md) oraz [docs/DISASTER_RECOVERY.md](docs/DISASTER_RECOVERY.md).

## Licencje

Informacje o upstreamie i zależnościach: `THIRD_PARTY_NOTICES.md`.
