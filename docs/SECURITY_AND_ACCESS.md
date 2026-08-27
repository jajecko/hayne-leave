# HAYNE Leave — Security and Access

## Model bezpieczeństwa

System przetwarza dane pracownicze i informacje o nieobecnościach. Dostęp ma być zgodny z zasadą najmniejszych uprawnień, a szczegóły nieobecności wrażliwych nie mogą być ujawniane szerszej grupie niż wymaga workflow.

## Uwierzytelnianie

HAYNE AD korzysta z LDAPS. Konfiguracja obejmuje hosty AD, port 636, base DN, konto bind i plik CA. Hasło bind jest sekretem deploymentu i nie może znaleźć się w Git.

Dwa niezależne mechanizmy/gate'y:

- synchronizacja katalogu użytkowników;
- interaktywne logowanie użytkownika do AD.

Nie należy ich traktować jako jednego przełącznika.

## Break-glass

Co najmniej jedno lokalne konto administracyjne musi pozostać zdolne do logowania bez dostępności AD. Lista jest kontrolowana przez `HAYNE_AD_AUTH_LOCAL_LOGINS` oraz `HAYNE_AD_PROTECTED_LOGINS`. Zmiana tych wartości wymaga świadomego review bezpieczeństwa.

## Sekrety

Sekrety przechowujemy wyłącznie w produkcyjnym `.env`/bezpiecznym magazynie deploymentu. Dotyczy to w szczególności:

- `MYSQL_PASSWORD`;
- `MYSQL_ROOT_PASSWORD`;
- `ENC_KEY`;
- `HAYNE_AD_BIND_PASSWORD`;
- `VAPID_PRIVATE_KEY`;
- ewentualnych poświadczeń SMTP.

`.env.example` zawiera wyłącznie nazwy i bezpieczne placeholdery.

## TLS i certyfikaty

Publicznym punktem wejścia użytkownika jest HTTPS. Certyfikat reverse proxy i CA do LDAPS pełnią inne role i nie wolno ich mieszać. Prywatne klucze TLS nie należą do repo. `LDAP_CA_HOST_DIR` montuje wyłącznie materiały zaufania wymagane przez klienta LDAPS.

## Role

Uprawnienia aplikacyjne muszą być walidowane po stronie serwera. Ukrycie przycisku/menu nie jest kontrolą bezpieczeństwa. Szczególną uwagę należy zachować dla:

- administratora;
- HR;
- managera zatwierdzającego;
- pracownika;
- operacji wykonywanych przez HR w imieniu innego pracownika.

## Samoakceptacja

Workflow nie może pozwalać użytkownikowi zaakceptować własnego wniosku tylko dlatego, że posiada rolę managera/HR. Guard samoakceptacji jest kontrolą bezpieczeństwa, nie kosmetyką UI.

## Prywatność danych szczególnych

Powód i szczegóły urlopu opiekuńczego oraz podobne pola o zwiększonej wrażliwości powinny być ujawniane tylko tam, gdzie jest to konieczne. E-mail do managera i powierzchnia review nie powinny kopiować zbędnych danych szczególnych.

## Operacje write-gated

Synchronizacja AD i kalendarza posiadają tryby read-only/plan oraz osobne flagi apply. Domyślnym bezpiecznym zachowaniem jest brak zapisu. Zmiana gate'a na write musi być jawna, odwracalna i poprzedzona preview.

## Audyt zmian

Zmiany bezpieczeństwa wymagają:

1. osobnego uzasadnienia w PR;
2. testu negatywnego (co ma być zabronione);
3. testu pozytywnego;
4. aktualizacji dokumentacji;
5. braku sekretów w diffie.

## Minimalny przegląd okresowy

Okresowo należy sprawdzać:

- listę kont lokalnych/break-glass;
- aktywne role administrator/HR;
- poprawność certyfikatów i dat ważności;
- dostępność obu kontrolerów AD;
- logi błędów uwierzytelniania;
- konfigurację SMTP relay;
- czy `.env`, dumpy DB i prywatne klucze nie są śledzone przez Git.
