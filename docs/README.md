# HAYNE Leave — dokumentacja kanoniczna

Ten katalog jest punktem wejścia do dokumentacji systemu HAYNE Leave. Dokumentacja ma opisywać **aktualny system**, a nie wyłącznie historię kolejnych PR-ów.

## Dokumenty kanoniczne

1. [SYSTEM_OVERVIEW.md](SYSTEM_OVERVIEW.md) — zakres produktu, komponenty, odpowiedzialności i granice systemu.
2. [OPERATIONS_RUNBOOK.md](OPERATIONS_RUNBOOK.md) — uruchamianie, aktualizacja, diagnostyka, backup/restore, awarie i rollback.
3. [SECURITY_AND_ACCESS.md](SECURITY_AND_ACCESS.md) — model dostępu, AD/LDAPS, sekrety, break-glass, TLS i dane wrażliwe.
4. [DEVELOPMENT_AND_RELEASE.md](DEVELOPMENT_AND_RELEASE.md) — zasady zmian, testów, PR, CI, migracji i release.
5. [BUSINESS_RULES.md](BUSINESS_RULES.md) — kanoniczny opis logiki urlopowej i workflow.
6. [INTEGRATIONS.md](INTEGRATIONS.md) — AD/LDAPS, SMTP, kalendarz świąt, PWA/Web Push i zależności zewnętrzne.
7. [DISASTER_RECOVERY.md](DISASTER_RECOVERY.md) — scenariusz odtworzenia systemu od zera.
8. [DOCUMENTATION_GOVERNANCE.md](DOCUMENTATION_GOVERNANCE.md) — reguły utrzymywania dokumentacji przy każdej zmianie.

## Dokumentacja historyczna

`docs/checkpoints/` zawiera checkpointy implementacyjne i dowody wykonania konkretnych zmian. Są wartościowym audyt trail, ale **nie są źródłem prawdy o aktualnym systemie**. Jeśli checkpoint jest sprzeczny z dokumentem kanonicznym lub aktualnym kodem, obowiązuje aktualny kod i dokument kanoniczny powinien zostać poprawiony w tym samym PR.

## Definition of Done dla dokumentacji

Zmiana nie jest kompletna, jeżeli wpływa na zachowanie, konfigurację, operacje, bezpieczeństwo, dane, integrację lub procedurę wdrożenia, a odpowiedni dokument kanoniczny nie został zaktualizowany.
