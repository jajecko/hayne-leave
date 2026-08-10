# HAYNE Leave

Firmowy system obsługi urlopów HAYNE oparty na Jorani.

## Architektura

Repozytorium nie kopiuje całego upstreamu Jorani. Obraz aplikacji pobiera przypięte wydanie `v1.0.4`, instaluje jego zależności i dopiero potem nakłada małą warstwę HAYNE:

- `hayne/overlay/` — własne assety i CSS,
- `hayne/patches/` — minimalne zmiany w widokach Jorani,
- `Dockerfile` — budowa aplikacji z przypiętego upstreamu,
- `docker/mysql/Dockerfile` — baza z oficjalnymi skryptami inicjalizacyjnymi tej samej wersji Jorani.

Jeśli po przyszłej zmianie wersji upstream któryś patch przestanie pasować, build/CI ma zakończyć się błędem zamiast po cichu pominąć branding.

## Branding v1

Pierwszy slice zmienia wyłącznie warstwę prezentacji:

- logo w górnym pasku na HAYNE,
- logo i nazwę produktu na ekranie logowania,
- tytuł strony na `HAYNE Leave`,
- podstawową czarno-białą warstwę wizualną HAYNE dla nawigacji i przycisków.

Logika urlopowa, workflow i model danych pozostają po stronie Jorani.

## Uruchomienie lokalne

Wymagany jest Docker z Docker Compose.

```bash
cp .env.example .env
docker compose up --build
```

Po uruchomieniu aplikacja jest dostępna domyślnie pod `http://localhost:8080`.

Przed użyciem poza lokalnym środowiskiem należy zmienić co najmniej `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `ENC_KEY`, `BASE_URL` i ustawienia SMTP w `.env`.

## Wersja upstream

Aktualnie: **Jorani v1.0.4**.

Licencja upstream i wymagane informacje znajdują się w `THIRD_PARTY_NOTICES.md`.
