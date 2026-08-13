# PR-LEAVE-09 — ręczna korekta wykorzystania urlopów

## Cel

Uruchomienie HAYNE Leave w trakcie roku wymaga uwzględnienia urlopów faktycznie wykorzystanych przez pracowników, ale obsłużonych wcześniej papierowo. HR ma korygować wykorzystanie per pracownik i rok bez odtwarzania sztucznych dat wniosków.

## UX

Na liście pracowników w `Limity urlopowe` każdy pracownik ma akcję `Koryguj wykorzystanie`.

Ekran korekty pokazuje osobno:

- urlop wypoczynkowy — łączna liczba dni wykorzystanych papierowo,
- urlop na żądanie — liczba dni będąca częścią powyższego wykorzystania, maks. 4,
- urlop opiekuńczy — korekta rocznej puli 5 dni,
- siła wyższa — korekta rocznej puli 2 dni,
- opieka nad dzieckiem — korekta indywidualnie przyznanej puli 0/1/2 dni,
- urlop okolicznościowy — korekta konkretnego zdarzenia: rodzaj, data, wykorzystane dni,
- dzień wolny za święto — oznaczenie wykorzystania konkretnego grantu HR.

Typy bez salda/uprawnienia, np. L4, urlop bezpłatny i Home Office, nie są elementem korekty salda.

## Semantyka urlopu na żądanie

Urlop na żądanie nie jest dodatkową pulą. Jeżeli pracownik wykorzystał papierowo 8 dni urlopu wypoczynkowego, z czego 2 na żądanie, HR wpisuje:

- wykorzystano urlopu wypoczynkowego: 8,
- w tym na żądanie: 2.

Do puli 26 dni odejmowane jest łącznie 8, a podlimit na żądanie wynosi 2/4 wykorzystane. Nie występuje podwójne odjęcie.

## Model księgowy

Korekty są przechowywane w `hayne_usage_corrections` i audytowane w `hayne_usage_correction_history`.

Aby nie duplikować logiki księgowej, korekta jest materializowana jako techniczny zaakceptowany rekord `leaves` z przyczyną zaczynającą się od:

`[HAYNE_USAGE_CORRECTION|`

Dzięki temu istniejące mechanizmy nadal są jedynym źródłem obliczeń:

- `Hayne_leave_policy_model` i FIFO liczą korektę wypoczynkowego,
- `Hayne_on_demand_model` liczy korektę w limicie 4 dni,
- modele urlopu opiekuńczego, siły wyższej i opieki nad dzieckiem liczą korektę w swoich limitach,
- urlop okolicznościowy wykorzystuje istniejący klucz zdarzenie + data,
- dzień wolny za święto wykorzystuje istniejący `grant_id`.

Techniczne rekordy nie są zwykłymi wnioskami. Patch `270-hide-usage-corrections.patch` usuwa je z list wniosków, kalendarzy, wykrywania kolizji oraz głównych widoków obecności/raportów.

## Walidacja

Backend jest autorytatywny i blokuje m.in.:

- wartości ujemne i ułamkowe,
- `na żądanie > 4`,
- `na żądanie > łączny wypoczynkowy papierowy`,
- sumę realnych wniosków HAYNE + korekty większą od przyznanego wypoczynkowego,
- przekroczenie limitów ustawowych przez realne wnioski + korektę,
- opiekę nad dzieckiem bez przyznanej pracownikowi puli,
- korektę zdarzenia okolicznościowego ponad limit tego zdarzenia,
- oznaczenie grantu dnia za święto jako papierowo wykorzystanego, jeżeli ma już realny wniosek HAYNE.

Zapis korekt jest transakcyjny.

## Audyt

Każda zmiana wartości korekty zapisuje:

- pracownika,
- rok,
- rodzaj korekty,
- klucz zdarzenia/grantu, jeśli dotyczy,
- wartość poprzednią,
- wartość nową,
- operatora,
- czas zmiany.

## Guardrails

- brak rekonstrukcji fikcyjnych dat urlopu w interfejsie,
- brak widocznych technicznych wniosków,
- brak zmian znaczenia statusów Jorani,
- brak zmian AD,
- brak zmian kalendarza `dayoffs`,
- brak godzin i połówek dni,
- brak podwójnego odejmowania urlopu na żądanie,
- brak korekt dla typów bez rocznej puli lub konkretnego uprawnienia.

## Weryfikacja

Dedykowany workflow `verify-pr-leave-usage-correction` ma potwierdzić co najmniej:

1. build i lint nowych plików,
2. dostępność ekranu korekty dla administratora,
3. profil 26 dni,
4. korektę 8 dni wypoczynkowego, w tym 2 dni na żądanie,
5. materializację jako 6 dni zwykłego + 2 dni na żądanie,
6. łączne wykorzystanie 8 i saldo 18,
7. metadane na żądanie,
8. zapis audytu,
9. brak markera technicznego w widokach pracownika,
10. dostępność i styl arkusza UX korekty.
