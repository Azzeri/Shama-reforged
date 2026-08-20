Wszystko zaimplementowane i zweryfikowane. Podsumowanie:

Zaimplementowane:

laravel/reverb zainstalowany i skonfigurowany (BROADCAST_CONNECTION=reverb, wygenerowane klucze aplikacji).
Napotkałem po drodze realny bug: laravel/reverb w wersji ustawionej w locku nie startował przez konflikt z nowym mechanizmem frameworka blokującym pakiety przed rejestrowaniem komend "dev". Zaktualizowałem laravel/framework w ramach już dopuszczonego zakresu ^13.7 (13.16.1 → 13.26.1) — to naprawiło problem, framework to poprawił kilka patchy później.
App\Events\ShoppingListUpdated — ShouldBroadcastNow (bez kolejki, bo w produkcji i tak nie ma workera), publiczny kanał shopping-list.1.
Jedno miejsce podpięcia: ShoppingListItem::booted() nadaje broadcast na saved/deleted — łapie wszystkie ścieżki mutacji (toggle, dodanie, edycja, usunięcie, merge-with-day, generowanie z planu posiłków).
Listener w Livewire (#[On('echo:shopping-list.1,ShoppingListUpdated')]) — po prostu unieważnia cache i przelicza stronę na nowo z bazy.
resources/js/echo.js + wpięcie do app.js.
Nowy serwis reverb w .deploy/docker-compose.yml (współdzieli obraz z app przez YAML anchor, bez publikowania portu na zewnątrz), plus proxy WebSocket /app/ w nginx.
.deploy/up.sh — restart jeśli działa, start jeśli nie (styl i konwencja jak deploy.sh).
reverb:start dodany do composer run dev.
Zweryfikowane lokalnie na żywo: dwie osobne karty przeglądarki, dodanie pozycji na jednej pojawiło się na drugiej bez odświeżania — log Reverb pokazuje pełny przepływ (subskrypcja → broadcast → dostarczenie), zero błędów w konsoli.

Wymaga Twojej ręcznej akcji na serwerze produkcyjnym (nie mam dostępu do Twojego prawdziwego .env/domeny):

W produkcyjnym .env ustaw REVERB_HOST na Twoją prawdziwą domenę/IP, REVERB_PORT=80 (lub 443 jeśli dojdzie TLS), REVERB_SCHEME=http/https — teraz są tam wartości developerskie (localhost:8080).
Jeśli przed serwerem masz dodatkowy reverse proxy (np. Cloudflare) poza tym repo, on też musi przepuszczać nagłówki upgrade dla WebSocketów na ścieżce /app/.
Warto sprawdzić, czy kontener app potrafi połączyć się sam ze sobą przez publiczną domenę (server-side publish do Reverba używa tych samych zmiennych REVERB_HOST co przeglądarka) — jeśli to będzie problem w praktyce, dopiszę osobny wewnętrzny hostname (reverb:8080) dla tego kierunku.