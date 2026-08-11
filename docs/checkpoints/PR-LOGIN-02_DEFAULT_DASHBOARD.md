# PR-LOGIN-02 — dashboard jako domyślna strona

## Cel

Po wejściu na bazowy adres HAYNE Leave i poprawnym zalogowaniu użytkownik ma trafić na `Start` / dashboard, a nie na legacy listę wniosków.

## Root cause

Upstream Jorani ma:

```php
$route['default_controller'] = 'leaves';
```

Przy wejściu na `/` niezalogowany użytkownik trafia więc najpierw do kontrolera `leaves`. `setUserContext()` zapisuje `/` jako `last_page`, przekierowuje do logowania, a po poprawnym logowaniu Jorani wraca na `/`, które ponownie rozwiązuje się do `leaves`.

## Zmiana

`hayne/patches/180-default-dashboard-route.patch` zmienia pusty/default route na:

```php
$route['default_controller'] = 'pages/view';
```

`Pages::view()` ma domyślny argument `$page = 'home'`, więc bazowy adres aplikacji jest semantycznie równoważny z `Start` (`/home`). Nie dodajemy trzeciego segmentu do `default_controller`, bo CodeIgniter traktuje ten wpis jako `controller/method`, a nie jako pełną trasę z argumentem.

## Zachowanie deep linków

Nie zmieniamy `Connection::redirectToLastPage()` ani mechanizmu `last_page`. Bezpośrednie wejście na chroniony URL, np. `/leaves/counters`, nadal po zalogowaniu wraca do tej konkretnej strony.

## Weryfikacja

Dedykowany workflow `verify-pr-login-02` sprawdza dwa scenariusze na świeżych sesjach:

1. wejście na `/` → ekran logowania → poprawny login → dashboard z `data-hayne-home="v1"` i powitaniem;
2. wejście na `/leaves/counters` → ekran logowania → poprawny login → powrót do salda urlopowego.

Pierwszy run CI z `pages/view/home` wykazał stronę `Object not found`; po korekcie do `pages/view` test odtwarza właściwy mechanizm `Pages::view('home')`.

## Poza zakresem

- zmiana ekranu loginu;
- SAML/OAuth flow;
- logo;
- UI dashboardu;
- mechanizm `last_page` dla celowych deep linków.
