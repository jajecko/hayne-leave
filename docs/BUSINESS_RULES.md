# HAYNE Leave — Business Rules

Ten dokument jest mapą kanonicznych reguł biznesowych. Szczegóły implementacyjne mogą być rozwijane, ale zmiana zachowania wymaga aktualizacji tego pliku.

## Wspólne zasady

- Wniosek jest przypisany do konkretnego pracownika i typu nieobecności.
- Dni niepracujące wynikają z kalendarza pracy i nie powinny zużywać puli jak dzień roboczy, o ile polityka danego typu nie stanowi inaczej.
- Anulowany wniosek nie powinien blokować nowego wniosku przez overlap i nie powinien być prezentowany jako aktywna nieobecność w kalendarzu.
- Operacje wpływające na saldo muszą być deterministyczne i możliwe do audytu.
- Kontrola limitu musi istnieć po stronie serwera; ograniczenie formularza JS jest tylko UX.

## Urlop wypoczynkowy

System posiada roczne pule i rozlicza wykorzystanie w kolejności FIFO zgodnie z polityką HAYNE. Saldo powinno umożliwiać rozróżnienie źródła puli/roku, aby użytkownik i HR mogli zrozumieć, z czego pochodzi dostępny limit.

## Urlop na żądanie

Jest sub-limitem w ramach właściwej puli urlopowej, a nie niezależnym dodatkowym kredytem. System ma pilnować limitu zarówno przy tworzeniu, jak i edycji/zmianie wniosku.

## Urlop opiekuńczy

Posiada własną politykę/limit. Dane uzasadniające nieobecność są traktowane jako bardziej wrażliwe i podlegają ograniczeniu widoczności w review oraz powiadomieniach.

## Siła wyższa

Posiada własny limit/politykę oraz dedykowane pola i sposób rozliczenia. Walidacja limitu i danych musi działać po stronie serwera.

## Opieka nad dzieckiem — art. 188

Posiada dedykowaną politykę i limit. Sposób wykorzystania jest kontrolowany przez model HAYNE, a nie wyłącznie przez generyczne saldo Jorani.

## Urlop okolicznościowy

Typ posiada własne przesłanki/pola i limit wynikający z polityki. UI oraz backend muszą utrzymywać zgodność wymaganych danych.

## Odbiór dnia za święto

System obsługuje przyznawanie prawa do odbioru dnia wolnego, w tym operację grupową. Przyznanie i wykorzystanie muszą być rozdzielone i audytowalne.

## Wezwanie urzędowe / nieobecności ustawowe

Typy oznaczone polityką zwolnienia z kredytu nie powinny być blokowane przez brak zwykłego salda urlopowego. To zachowanie wynika z registry/policy, a nie z wyjątków rozsianych po UI.

## Registry typów nieobecności

`Hayne_leave_type_registry_model` i odpowiadający schemat HAYNE definiują zachowanie typów: dostępność przy tworzeniu nowego wniosku, tryb salda/kredytu oraz routing workflow. Nowe typy powinny być dodawane przez registry/policy zamiast przez kolejne niespójne warunki w widokach.

## Workflow i zatwierdzanie

- pracownik składa wniosek;
- właściwy przełożony/rola wykonuje review zgodnie z routingiem;
- samoakceptacja jest zabroniona;
- HR może posiadać szersze uprawnienia operacyjne, ale operacja w imieniu pracownika musi zachować prawidłową tożsamość właściciela wniosku i audyt;
- powiadomienia e-mail/push są skutkiem zmiany stanu, a nie źródłem prawdy o stanie.

## Korekty wykorzystania

Ręczne korekty są operacją administracyjną i powinny pozostawiać jednoznaczny ślad. Nie mogą po cichu maskować błędu podstawowej logiki salda.

## Kalendarz pracy

Kalendarz HAYNE obejmuje weekendy i polskie święta. Synchronizacja zewnętrzna jest narzędziem do utrzymania kalendarza, ale wynik zapisany w systemie jest używany do obliczeń urlopowych.

## Zasada rozszerzania

Przy dodaniu nowego typu nieobecności należy określić co najmniej:

- czy jest dostępny dla nowego wniosku;
- czy wymaga kredytu;
- jaki ma limit i okres resetu;
- czy wykorzystuje godziny/dni;
- jakie ma pola dodatkowe;
- kto widzi pola wrażliwe;
- jaki ma workflow;
- jak wpływa na kalendarz;
- jakie wysyła powiadomienia;
- jak zachowuje się przy anulowaniu i edycji.
