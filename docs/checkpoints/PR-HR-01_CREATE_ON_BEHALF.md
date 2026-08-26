# PR-HR-01 — wniosek składany przez HR w imieniu pracownika

Data: 2026-08-26

## Wymaganie

Osoba z rolą HR musi móc złożyć indywidualny wniosek w imieniu dowolnego aktywnego pracownika. Zakres nie może zależeć od relacji `users.manager`, delegacji ani struktury podwładnych.

## Stan przed zmianą

Jorani udostępniał globalnej roli HR dwie stare ścieżki:

- `Zespół → Lista pracowników → Utwórz wniosek`,
- `/hr/leaves/create/{employee_id}`.

Technicznie pozwalały one wskazać dowolnego pracownika, ale zapisywały wniosek poza głównym procesem HAYNE. Omijały między innymi serwerowe przeliczenie pełnych dni, filtr aktywnych rodzajów nieobecności, polityki rocznych limitów i zapis metadanych urlopów ustawowych. Z kolei główny ekran `/leaves/create` zawsze używał identyfikatora zalogowanej osoby.

## Rozwiązanie

Główny formularz `/leaves/create` jest jedyną kanoniczną ścieżką indywidualnego tworzenia wniosku:

- HR widzi na jego początku selektor wszystkich aktywnych użytkowników, posortowanych po nazwisku i imieniu;
- wybór pracownika przeładowuje formularz z `?employee={id}`, dzięki czemu rodzaje nieobecności, saldo, granty i limity są liczone dla właściciela wniosku;
- POST zawiera `employee`, ale backend honoruje ten parametr wyłącznie dla roli HR;
- zwykły pracownik, nawet po ręcznej zmianie query stringa lub POST, pozostaje właścicielem własnego wniosku;
- zapis używa istniejącego procesu HAYNE: pełne dni, przeliczenie czasu na serwerze, polityki ustawowe, metadane, idempotency token oraz routing akceptacji;
- po zapisie w imieniu innej osoby HR trafia na listę wniosków tego pracownika;
- przy włączonej historii `changed_by` wskazuje konto HR, natomiast `leaves.employee` pozostaje pracownikiem, którego dotyczy wniosek.

Stare indywidualne wejścia HR i HR-owe wejście przez listę podwładnych przekierowują do formularza kanonicznego. Stary endpoint zbiorczy zwraca `410 Gone`, ponieważ nie potrafi bezpiecznie zebrać indywidualnych danych wymaganych przez część ustawowych rodzajów nieobecności.

## Semantyka statusu

HR składa wniosek tak samo jak pracownik:

- `Wyślij wniosek` tworzy status `Requested` i uruchamia właściwy routing akceptacji;
- `Zapisz jako plan` tworzy status `Planned` bez automatycznego zatwierdzenia.

Zmiana nie wprowadza możliwości automatycznej akceptacji własnej decyzji przez HR i nie osłabia zabezpieczenia przed self-approval.

## Zakres bezpieczeństwa

- tylko aktywny pracownik może być celem;
- lista celu nie jest ograniczona do podwładnych — jest globalna dla HR;
- identyfikator celu jest ponownie sprawdzany na serwerze;
- wszystkie odwołania do kontraktu, kalendarza, salda i polityk korzystają z `employeeId` właściciela;
- e-mail i kolejka akceptacji wynikają z właściciela oraz `workflow_mode` rodzaju nieobecności;
- szkic formularza w `sessionStorage` ma osobny klucz per pracownik, aby dane nie przechodziły między osobami.

## Weryfikacja

Workflow `verify-pr-hr-create-on-behalf` sprawdza:

1. składnię finalnego źródła po nałożeniu całego stosu patchy;
2. widoczność globalnego selektora dla HR i wybór pracownika `id=2`;
3. zapis wniosku na pracownika `id=2` przy aktorze HR `id=1`;
4. ponowne obliczenie przesłanego fałszywego `duration=999` do rzeczywistego `1.000`;
5. zapis `changed_by=1` w historii;
6. zignorowanie sfałszowanego `employee=1` na koncie zwykłego użytkownika `id=2`;
7. zamknięcie starego zbiorczego endpointu kodem `410`;
8. brak błędów PHP i wyjątków w logach kontenerów.

## Poza zakresem

- zbiorcze tworzenie jednego wniosku dla wielu osób;
- zmiany modelu akceptacji i delegacji;
- zmiany schematu bazy danych;
- automatyczne zatwierdzanie wniosku tworzonego przez HR.
