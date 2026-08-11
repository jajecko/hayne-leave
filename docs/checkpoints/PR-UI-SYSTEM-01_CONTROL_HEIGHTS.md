# PR-UI-SYSTEM-01 — standardowa wysokość kontrolek

## Cel

Ujednolicić geometrię przycisków i standardowych kontrolek na zalogowanych legacy powierzchniach HAYNE Leave. Bez tej zmiany Bootstrap 2 liczył `min-height` przycisku w modelu content-box, więc np. `min-height: 40px` + pionowy padding dawały przyciski blisko 60px obok 44px inputów.

## Zakres

`hayne/overlay/assets/hayne/foundation.css`:

- bazowy token desktop pozostaje `--hayne-control-height: 44px`;
- standardowe inputy/selecty dostają dokładne `height` oraz `box-sizing: border-box`;
- standardowe `.btn` dostają dokładne `height`, `min-height` i `box-sizing: border-box`;
- pionowy padding przycisków zostaje usunięty, dzięki czemu nie zwiększa realnej wysokości;
- Select2, kontrolki DataTables oraz przyciski DataTables (`.dt-button`) korzystają z tego samego tokenu;
- mobile używa wspólnego tokenu `46px` dla przycisków i kontrolek, bez osobnego niższego `min-height` przycisku.

Warstwa `legacy-compat.css` nadal odpowiada za `inline-flex`, grupy przycisków i `input-append/input-prepend`, więc wszystkie elementy dziedziczą wspólną wysokość bez zmiany legacy HTML.

## Szczególnie sprawdzana powierzchnia

`/hr/employees` (`Lista pracowników`):

- `Wybierz`;
- `Wszyscy / Aktywni / Nieaktywni`;
- przyciski kierunku dat i resetu;
- pola dat;
- `Zaznaczenie`, `Eksportuj listę`, `Utwórz`;
- `Liczba pozycji na stronie / Page length` i `Zmień kolumny / Change columns` generowane przez DataTables.

Wszystkie te standardowe elementy powinny mieć wspólną wysokość 44px na desktopie.

## Weryfikacja

`verify-admin-surfaces` został rozszerzony tak, aby zmiana `foundation.css` uruchamiała pełny smoke 21 głównych tras zalogowanej aplikacji oraz zapisywała reprezentatywne screenshoty, w tym `hr-employees.png`.

Pierwszy screenshot CI potwierdził wyrównanie standardowych `.btn` i pól dat, a następnie PR objął również mniejsze przyciski DataTables, które nie korzystają z klasy Bootstrap `.btn`.

CI sprawdza również obecność tokenu 44px i reguły dokładnej wysokości w zbudowanym obrazie.

## Poza zakresem

- dedykowane komponenty targetowe mające własną, świadomie zaprojektowaną geometrię (np. formularz `hayne-request-page`);
- login, który pozostaje większym komponentem auth;
- zmiana treści/semantyki przycisków;
- redirect po loginie;
- podmiana logo.
