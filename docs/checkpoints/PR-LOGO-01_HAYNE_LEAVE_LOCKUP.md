# PR-LOGO-01 — nowy lockup HAYNE Leave

## Cel

Podmienić wspólny logotyp HAYNE Leave na lockup dostarczony przez użytkownika: duże `HAYNE`, a poniżej wyśrodkowane `LEAVE` pomiędzy dwiema poziomymi liniami.

## Źródło wizualne

Logo zostało odwzorowane z przekazanego wzoru jako czyste wektory. Finalny plik nie korzysta z fontów systemowych, bitmap ani zewnętrznych zasobów.

## Zmiana

Podmieniony zostaje jeden wspólny plik:

`hayne/overlay/assets/hayne/logo.svg`

Nowy SVG:

- ma `viewBox="0 0 1304 343"`, odpowiadający proporcjom wzoru;
- zachowuje dostępny tytuł `HAYNE LEAVE`;
- ma przezroczyste tło;
- składa się wyłącznie z czarnych ścieżek wektorowych;
- nie zawiera `<text>` ani `<image>`, więc wygląd nie zależy od zainstalowanych fontów ani plików rastrowych.

## Zasięg

Ponieważ aplikacja już korzysta z jednego wspólnego `assets/hayne/logo.svg`, podmiana obejmuje bez duplikowania assetów:

- ekran logowania;
- nawigację zalogowanej aplikacji;
- session failure;
- prosty login OAuth;
- ekran OAuth authorize.

Nie rekonstruujemy osobno słowa `Leave` w HTML/JS.

## Rozmiary

Nie zmieniamy istniejących reguł rozmiaru. Nowy lockup ma proporcje bardzo zbliżone do poprzedniego assetu, więc obecne ograniczenia `width` dla loginu i nawigacji pozostają właściwe.

## Weryfikacja

Istniejący workflow `verify-pr-login-01` został zaktualizowany tak, aby:

- walidować nowy `viewBox` i wektorowy charakter assetu;
- potwierdzać brak zależności od `<text>` i `<image>`;
- potwierdzać, że wszystkie powierzchnie auth/navigation wskazują ten sam `logo.svg`;
- renderować login desktop 1440×1000 i mobile 390×844;
- zapisywać artefakt `pr-logo-01-evidence` do ręcznej kontroli wizualnej.

## Poza zakresem

- zmiana faviconu;
- zmiana typografii aplikacji;
- zmiana rozmiaru login card;
- zmiana routingu lub zachowania logowania;
- dodatkowe warianty kolorystyczne logo.
