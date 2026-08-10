# PR-UI-10 — HAYNE approvals queue

## Cel

Doprowadzić główną kolejkę managera `/requests` do systemu wizualnego HAYNE bez przepisywania logiki akceptacji Jorani.

## Kontrakt upstream

- `/requests` przekierowuje do kolejki oczekującej,
- `/requests/requested` pokazuje wnioski `Requested` i prośby `Cancellation`,
- `/requests/all` pozwala przeglądać wszystkie statusy,
- akcje pozostają oparte na klasach `.lnkAccept`, `.lnkReject`, `.lnkCancellationAccept`, `.lnkCancellationReject`,
- endpointy pozostają `requests/accept/<id>`, `requests/reject/<id>`, `requests/acceptCancellation/<id>`, `requests/rejectCancellation/<id>`,
- komentarz odrzucenia nadal korzysta z `#frmRejectLeaveForm`,
- filtrowanie nadal używa `#cboLeaveType`, `.filterStatus` i oryginalnych indeksów DataTables.

## Zakres UI

- topbar i nagłówek `Do akceptacji`,
- zakładki `Oczekujące / Wszystkie`,
- wyszukiwarka i panel `Filtry`,
- tabela prezentowana jako `Pracownik / Typ / Data od / Data do / Dni / Status / Akcje`, przy zachowaniu oryginalnej kolejności danych w DOM/DataTables,
- avatar z inicjałami i line-icon typu nieobecności,
- bezpośrednie akcje `Akceptuj / Odrzuć`,
- historia pozostaje dostępna, jeśli upstream ją włączył,
- export i ICS pozostają w panelu filtrów,
- polski empty state, licznik i paginacja.

## Guardrails

- bez zmian `Requests` controller, `Leaves_model`, bazy i endpointów,
- nie podmieniamy ani nie klonujemy linków akcji — presentation enhancer modyfikuje tekst/klasy na istniejących elementach, dzięki czemu wcześniej podpięte event handlery pozostają na tych samych node'ach,
- nie zmieniamy indeksów kolumn używanych przez filtr typu/statusu,
- nie omijamy Bootbox confirmation ani formularza komentarza przy odrzuceniu,
- nie tworzymy przykładowych wniosków,
- realny screenshot CI musi zostać ręcznie obejrzany przed merge.

## Weryfikacja

Główny `verify` nadal wykonuje pełny regression suite. Dodatkowy path-scoped workflow `verify-ui10-approvals` buduje aplikację, sprawdza obecność klas i endpointów approval workflow w zbudowanym obrazie, loguje się prawdziwym flow CI, pobiera `/requests` i generuje `hayne-approvals.png` w viewport 1440×1000.
