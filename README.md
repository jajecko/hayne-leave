# HAYNE Leave

Firmowy system obsługi urlopów HAYNE oparty na [Jorani](https://github.com/jorani/jorani).

## Założenia

- upstream Jorani jest przypięty do konkretnej wersji zamiast kopiowany do repozytorium,
- branding HAYNE jest nakładany jako mała, jawna warstwa zmian,
- aktualizacja upstream ma kończyć się błędem, jeśli któryś patch brandingowy przestanie pasować,
- logika urlopowa Jorani pozostaje oddzielona od wyglądu HAYNE.

Pierwszy etap: HAYNE branding shell dla Jorani v1.0.4.
