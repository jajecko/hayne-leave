# PR-LEAVE-01 — pełne dni i fundament limitów

## Cel

Pierwsza działająca wersja polskiej polityki urlopowej HAYNE Leave bez przebudowy silnika Jorani.

## Założenia biznesowe

- HAYNE Leave obsługuje wyłącznie całe dni.
- Brak połówek dnia i brak rozliczeń godzinowych.
- Jorani `entitleddays` pozostaje źródłem kredytów/limitów urlopowych.
- Operator zarządza limitami z listy pracowników.
- FIFO rocznych pul, automatyczne odnowienie roczne i urlop na żądanie jako podlimit wypoczynkowego są kolejnymi etapami i nie są implementowane w tym PR.

## Zmiany

### Pełne dni

- nowy `assets/hayne/full-day.js` wymusza w UI:
  - start dnia = `Morning`,
  - koniec dnia = `Afternoon`,
  - ukrycie kontrolek części dnia,
  - integer input mode dla liczby dni.
- backend `Leaves.php` ignoruje zmanipulowane wartości części dnia i ponownie ustawia pełny dzień;
- backend odrzuca wynik kalkulacji zawierający ułamek dnia.

### Limity urlopowe

- w menu Administracja / Urlopy pojawia się `Limity urlopowe`, prowadzące do istniejącej listy pracowników HR;
- wykorzystujemy istniejący mechanizm Jorani dodawania `entitleddays` dla zaznaczonych pracowników;
- endpointy `Entitleddays` odrzucają wartości niecałkowite.

## Poza zakresem

- automatyczne 20/26 dni na podstawie stażu;
- automatyczne proporcje dla zatrudnienia w trakcie roku;
- automatyczny rollover na kolejny rok;
- FIFO rozliczania zaległych rocznych pul;
- urlop na żądanie 4 dni jako podlimit wypoczynkowego;
- godziny i części dnia;
- zmiany schematu bazy danych.

## Następny krok

PR-LEAVE-02: roczne pule wypoczynkowe + automatyczny rollover + FIFO od najstarszej puli.

PR-LEAVE-03: urlop na żądanie jako licznik 4 dni konsumujący najstarszą pulę wypoczynkowego.
