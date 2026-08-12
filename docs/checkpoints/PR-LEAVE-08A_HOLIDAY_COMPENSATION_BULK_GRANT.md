# PR-LEAVE-08A — zbiorcze przyznawanie dnia wolnego za święto

## Cel

Usunąć ręczne przyznawanie DWS pracownik po pracowniku. HR definiuje święto i okres rozliczeniowy raz, a HAYNE przyznaje po jednym grancie wszystkim aktywnym pracownikom.

## Zakres

- formularz `/hayneholidays` bez wyboru pojedynczego pracownika,
- pokazanie liczby aktywnych pracowników objętych operacją,
- `saveGrant()` pobiera wszystkich aktywnych pracowników i wywołuje istniejący idempotentny zapis grantu dla każdego,
- cała operacja działa w jednej transakcji,
- istniejący model per-pracownik pozostaje bez zmian,
- brak zmian w `entitleddays`, FIFO, 20/26, urlopie na żądanie i logice wniosków,
- brak zmian w schemacie SQL.

## Uzasadnienie modelu

Nie wprowadzamy jednego globalnego grantu współdzielonego przez wszystkich. Każdy pracownik nadal dostaje osobny rekord w `hayne_holiday_compensation_grants`, ponieważ rezerwacja i wykorzystanie dnia są indywidualne. Zmienia się wyłącznie sposób administracyjnego utworzenia tych rekordów — z N ręcznych operacji na jedną operację zbiorczą.

## Idempotencja i bezpieczeństwo

Unikalność `(employee_id, source_holiday_date)` pozostaje bez zmian. Ponowne uruchomienie dla tej samej daty i tego samego okresu nie tworzy duplikatów. Jeśli istniejący użyty grant miałby zostać przesunięty do innego okresu, istniejąca walidacja odrzuca zmianę; transakcja zbiorcza wycofuje wtedy całą operację.

## Produkcja

Po merge wymagany jest rebuild kontenera aplikacji na QNAP. Baza nie wymaga nowej migracji, ponieważ schemat nie ulega zmianie.
