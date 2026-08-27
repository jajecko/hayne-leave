# REPORT-HR-01 — miesięczny raport nieobecności dla kadr

## Cel

Dodać do HAYNE Leave operacyjny raport miesięczny dla kadr/księgowości zgodny z zatwierdzonym wzorem `HAYNE_Leave_raport_miesieczny_kadry_PROSTY.xlsx`.

Raport jest jedną tabelą i ma identyczny kontrakt kolumn:

1. ID wniosku
2. Pracownik
3. Dział
4. Rodzaj nieobecności
5. Od
6. Do
7. Dni robocze w miesiącu
8. Godziny w miesiącu
9. Status
10. Data złożenia
11. Zatwierdził
12. Data decyzji
13. Anulowanie
14. Kod płacowy

Domyślnym okresem jest poprzedni miesiąc kalendarzowy. HR może wybrać inny miesiąc i pobrać ten sam zestaw danych jako XLSX lub CSV.

## Dostęp

Nowa powierzchnia `haynehrreport/*` jest dostępna wyłącznie dla użytkownika z rolą HR lub administratora. Kontrola jest wykonywana po stronie serwera w konstruktorze kontrolera; samo ukrycie pozycji menu nie jest mechanizmem autoryzacji.

## Źródło danych i statusy

Raport pokazuje rekordy z `leaves`, które:

- nachodzą na wybrany miesiąc,
- mają bieżący status `LMS_ACCEPTED` albo `LMS_CANCELLATION`,
- nie są technicznymi korektami migracyjnymi oznaczonymi `cause LIKE '[HAYNE_USAGE_CORRECTION|%'`.

W efekcie nie są pokazywane wnioski planowane, oczekujące, odrzucone i w pełni anulowane. `LMS_CANCELLATION` pozostaje skuteczną nieobecnością do momentu zatwierdzenia anulowania, dlatego w raporcie ma status `Anulowanie w toku` i pole `Anulowanie = W toku`.

## Granica miesiąca i kalendarz pracy

REPORT-HR-01 nie wprowadza drugiego silnika czasu pracy.

Dla każdego wniosku zakres jest najpierw przycinany do wybranego miesiąca. Jeżeli przycięcie odcina początek wniosku, początek zakresu raportowego staje się pełnym początkiem dnia (`Morning`). Jeżeli odcina koniec, koniec zakresu raportowego staje się pełnym końcem dnia (`Afternoon`). Oryginalne połówki dnia pozostają zachowane, jeżeli przypadają wewnątrz raportowanego miesiąca.

Następnie model korzysta z istniejących źródeł Jorani/HAYNE:

- `Dayoffs_model::listOfDaysOffBetweenDates()` — kalendarz umowy pracownika,
- `Leaves_model::actualLengthAndDaysOff()` — kanoniczne liczenie czasu w dniach.

Oznacza to, że soboty, niedziele, święta i ręcznie zarządzane dni wolne są rozliczane z tego samego `dayoffs`, którego używa formularz wniosku.

## Godziny

Obecny produkt HAYNE rejestruje nieobecności w dniach/połówkach dnia i nie posiada kanonicznego źródła absencji godzinowych. Z tego powodu `Godziny w miesiącu` ma wartość `0`.

Nie stosujemy sztucznego przelicznika `1 dzień = 8 godzin`. Gdy w produkcie pojawi się rzeczywiste źródło godzin, kolumna może zostać zasilona bez zmiany kontraktu eksportu.

## Audyt

Dane audytowe pochodzą wyłącznie z `leaves_history`:

- `Data złożenia` = pierwszy wpis ze statusem `LMS_REQUESTED`; fallback to data utworzenia rekordu,
- `Zatwierdził` = aktor pierwszego wpisu ze statusem `LMS_ACCEPTED`,
- `Data decyzji` = data tego samego wpisu akceptacji.

Dzięki temu późniejsze przejście do `LMS_CANCELLATION` nie niszczy informacji o pierwotnej decyzji.

## Prywatność

Zapytanie źródłowe wybiera wyłącznie pola niezbędne do raportu. Nie są eksportowane m.in.:

- uzasadnienia (`cause` jest używane tylko w warunku wykluczającym techniczne korekty),
- komentarze,
- dokumenty/załączniki,
- dane osoby wymagającej opieki,
- adresy,
- powody opieki i dane zdrowotne,
- metadane szczegółowe poszczególnych rodzajów absencji.

## Kody płacowe

Plik `legacy/application/config/hayne_payroll_codes.php` jest jedynym miejscem mapowania typu HAYNE na kod płacowy dla raportu.

W REPORT-HR-01 wszystkie wartości są celowo puste. W zatwierdzonym materiale źródłowym mapowanie ma status `DO USTALENIA`; przykładowe `UW`, `L4`, `SW` z wierszy demonstracyjnych nie są traktowane jako produkcyjna konfiguracja.

Po jednorazowym uzgodnieniu z księgowością uzupełniamy wyłącznie tę konfigurację — bez zmiany logiki raportu.

## XLSX

Eksport wykorzystuje istniejący PhpSpreadsheet dostarczany przez Jorani. Nie dodajemy nowej zależności.

Arkusz `Raport` zawiera:

- tytuł `HAYNE Leave — raport miesięczny dla kadr`,
- okres raportu,
- datę wygenerowania,
- użytkownika generującego,
- źródło `HAYNE Leave`,
- dokładnie 14 zatwierdzonych kolumn,
- tylko skuteczne rekordy,
- końcową informację o zasadzie miesiąca i prywatności.

## Kryteria akceptacji

- HR/admin może otworzyć raport; zwykły pracownik otrzymuje HTTP 403.
- Domyślny okres to poprzedni miesiąc.
- Zaakceptowana nieobecność jest raportowana.
- `LMS_CANCELLATION` jest raportowany jako `Anulowanie w toku` / `W toku`.
- `LMS_PLANNED`, `LMS_REQUESTED`, `LMS_REJECTED` i `LMS_CANCELED` nie są raportowane.
- `[HAYNE_USAGE_CORRECTION|...]` nie trafia do raportu.
- Wniosek przechodzący przez granicę miesiąca jest liczony tylko w wybranym miesiącu.
- Liczenie dni używa istniejącego kalendarza `dayoffs`.
- Uzasadnienia i dane wrażliwe nie są obecne w HTML/XLSX/CSV.
- Data złożenia, zatwierdzający i data decyzji pochodzą z historii.
- Kody płacowe pozostają puste do czasu zatwierdzenia mapowania.
- XLSX i CSV mają ten sam kontrakt 14 kolumn.
