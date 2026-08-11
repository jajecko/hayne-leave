# PR-UI-10 — HAYNE approvals queue

## Cel

Doprowadzić główną kolejkę managera `/requests` do systemu wizualnego HAYNE bez przepisywania logiki akceptacji Jorani.

## Kontrakt upstream

- `/requests` przekierowuje do kolejki oczekującej,
- `/requests/requested` pokazuje wnioski `Requested` i prośby `Cancellation`,
- `/requests/all` pozwala przeglądać wszystkie statusy,
- akcje pozostają oparte na klasach `.lnkAccept`, `.lnkReject`, `.lnkCancellationAccept`, `.lnkCancellationReject`,
- endpointy pozostają `requests/accept/<id>`, `requests/reject/<id>`, `requests/cancellation/accept/<id>`, `requests/cancellation/reject/<id>`,
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

Główny `verify` nadal wykonuje pełny regression suite. Dodatkowy path-scoped workflow `verify-ui10-approvals` buduje aplikację, sprawdza obecność klas i endpointów approval workflow w zbudowanym obrazie, loguje się prawdziwym flow CI, pobiera `/requests`, weryfikuje wyrenderowany DOM HAYNE i generuje `hayne-approvals.png` w viewport 1440×1000.

Finalna weryfikacja warstwy runtime/UI przed zamknięciem checkpointu:

- head runtime: `3214013f467549e47ccf39d3c5a269659678f4bf`,
- `verify` run #119 / `31459640750`: SUCCESS,
- `verify-ui10-approvals` run #6 / `31459640759`: SUCCESS,
- guard kontraktu approval: SUCCESS,
- logowanie i pobranie realnego `/requests`: SUCCESS,
- rendered-DOM guard: potwierdzone `data-hayne-view="approvals-v1"`, nagłówek `Do akceptacji` i polski empty state,
- `hayne-approvals.png` 1440×1000: ręcznie obejrzany i zaakceptowany,
- visual review potwierdził HAYNE shell, nagłówek, zakładki, wyszukiwarkę, panel filtrów, targetową tabelę, empty state i paginację,
- wykryty podczas review błąd inicjalizacji po DataTables został naprawiony presentation-only: enhancer wybiera rodzica `#leaves_wrapper` po owinięciu tabeli, bez zmian controller/model/DB/endpointów/handlerów/indeksów DataTables.

Zmiana checkpointu jest dokumentacyjna i uruchamia finalny CI ponownie; merge jest dozwolony dopiero po GREEN tego ostatniego rerunu.
