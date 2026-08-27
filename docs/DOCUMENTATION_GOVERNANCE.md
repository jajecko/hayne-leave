# HAYNE Leave — Documentation Governance

## Cel

Dokumentacja ma pozwolić nowemu developerowi/administratorowi zrozumieć, wdrożyć, utrzymać i odtworzyć system bez rekonstruowania projektu z PR-ów i rozmów.

## Rodzaje dokumentacji

### Kanoniczna

Pliki bezpośrednio w `docs/` opisują aktualny system. Muszą być aktualizowane razem z kodem.

### Checkpointy

`docs/checkpoints/` dokumentują zakończone slice'y/PR-y. Są immutable-ish audit trail: poprawiamy oczywiste błędy, ale nie przerabiamy ich na aktualną instrukcję systemu.

## Obowiązek aktualizacji

PR musi zmienić dokumentację, jeśli zmienia:

- architekturę lub komponenty;
- zmienne środowiskowe;
- procedurę deploy/rollback;
- model danych/migracje;
- role/uprawnienia/security guard;
- regułę biznesową;
- integrację;
- backup/restore;
- zachowanie użytkownika, którego nie da się oczywiście wywnioskować z UI.

## Reguła braku wiedzy z czatu

Informacja potrzebna do produkcyjnej operacji, diagnozy lub odtworzenia systemu nie może istnieć wyłącznie w rozmowie ChatGPT, terminal history albo pamięci administratora. Po ustaleniu takiej informacji należy przenieść ją do odpowiedniego dokumentu kanonicznego, bez zapisywania sekretów.

## Review dokumentacji

Reviewer powinien odpowiedzieć:

1. Czy dokument opisuje stan po merge, a nie stan sprzed zmiany?
2. Czy komendy są bezpieczne i rozróżniają produkcję od local?
3. Czy nie zawiera sekretów?
4. Czy wskazuje rollback i failure mode?
5. Czy nowa osoba może wykonać procedurę bez dodatkowych założeń?

## Nazewnictwo

Dokumenty kanoniczne używają stabilnych nazw tematycznych. Checkpointy mogą używać identyfikatorów PR/slice. Nie tworzymy kolejnego dokumentu kanonicznego tylko dlatego, że pojawił się nowy PR — aktualizujemy istniejący właściwy temat.

## Definition of Done projektu

Dokumentacja E2E jest kompletna dopiero gdy:

- wszystkie sekcje indeksu istnieją;
- zostały skonfrontowane z aktualnym kodem i `.env.example`;
- deployment production został zweryfikowany z runbookiem;
- wykonano próbny backup + restore;
- wykonano próbny DR na izolowanym środowisku;
- znane odstępstwa infrastrukturalne są opisane;
- proces PR wymusza dalsze utrzymywanie dokumentacji.
