# Faultline

[![CI](https://github.com/Tolemak/faultline/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/Tolemak/faultline/actions/workflows/ci.yml)

Własny tracker błędów dla moich aplikacji. Rozumie protokół Sentry, więc aplikacje zostają przy oficjalnych SDK Sentry i zmieniają tylko DSN. Symfony 8, PostgreSQL, Messenger.

Demo na żywo (tylko do odczytu, logowanie jednym kliknięciem): [faultline-demo.tolemak.pl](https://faultline-demo.tolemak.pl/demo)

[English version](README.md)

## Rozwój

Konfiguracja pochodzi ze zmiennych środowiskowych. Lokalnie skopiuj szablon i ustaw `APP_ENV=dev`, losowy `APP_SECRET` oraz `DATABASE_URL`:

```sh
cp .env.example .env.local
docker compose up -d
docker compose exec app composer install
docker compose exec app bin/console doctrine:migrations:migrate -n
docker compose exec app bin/console faultline:user:create admin
docker compose exec app bin/console messenger:consume async -vv
```

Aplikacja działa pod `http://127.0.0.1:8000` (port zmienia `APP_PORT`).

## Produkcja

Na serwerze uzupełnij wszystkie wartości w `container/.env` (czyta go Compose, nie trafia do obrazu) i uruchom skrypt wdrożenia (build, migracje, `web` + `worker` + `db`):

```sh
cp .env.example container/.env
container/deploy.sh
```

`web` nasłuchuje na `127.0.0.1:$FAULTLINE_PORT`, przed nim reverse proxy z TLS. Migracje uruchamia `deploy.sh`, nie start kontenera. CI wdraża pushe na `main`, gdy ustawione są sekrety `DEPLOY_*`.

Codzienny cron na hoście:

```sh
docker compose -f container/compose.yaml exec -T worker php bin/console faultline:purge
```

## Komendy

| Komenda | Do czego |
| --- | --- |
| `faultline:user:create <login>` | Tworzy admina albo zmienia mu hasło |
| `faultline:project:create <nazwa> [--origin=…] [--retention=30]` | Tworzy projekt i wypisuje DSN |
| `faultline:project:rotate-key <slug>` | Wymienia klucz i wypisuje nowy DSN |
| `faultline:issues:list [--project=<slug>] [--since=24h] [--new] [--status=…] [--limit=50] [--format=table\|json]` | Wypisuje problemy (tylko metadane, nigdy treść zdarzeń) do przeglądu z konsoli; `--since` przyjmuje datę ISO albo wiek (`90m`, `24h`, `7d`), `--new` stosuje go do pierwszego wystąpienia |
| `faultline:purge` | Usuwa zdarzenia po retencji i puste problemy |
| `faultline:demo:seed` | Czyści bazę i wgrywa syntetyczne dane demo (tylko z `FAULTLINE_DEMO=1`) |

## Integracje

- Telegram: ustaw `TELEGRAM_BOT_TOKEN` i `TELEGRAM_CHAT_ID`, żeby dostawać nowe problemy i regresje, najwyżej jedną wiadomość na problem na godzinę.
- Digest: ustaw `DIGEST_TOKEN` (min. 16 znaków) i wołaj `GET /api/digest` z nagłówkiem `Authorization: Bearer <token>`.

## Pasek statusu

`assets/tolemak-bar/tolemak-bar.js` to wspólny pasek statusu wszystkich aplikacji Tolemak: element `<tolemak-bar>` z dziećmi `<tolemak-field>`.

- Zwykłe custom elements bez zależności, więc ten sam plik działa w React, Twig i na stronach statycznych.
- Kolory pochodzą ze strony hosta przez własne właściwości `--tb-*`.
- Style to constructed stylesheets zamiast tagów `<style>`, więc pasek działa też przy restrykcyjnym CSP `style-src`.
- `langList()` jest metodą, nie getterem: React 19 przypisuje atrybuty jako właściwości, gdy element ma właściwość o tej nazwie.
- Aplikacje z własnym stanem motywu anulują zdarzenie `tolemak-theme` i same stosują motyw; w przeciwnym razie pasek ustawia `data-theme` i zapisuje wybór w `localStorage`. Przy wyłączonym storage wybór trwa do przeładowania.
- Język wymaga tłumaczeń aplikacji, więc pasek tylko ogłasza wybór.

## Instancja demo

Ustaw `FAULTLINE_DEMO=1` na osobnej instancji z własną bazą. Wtedy `/login` daje wejście do demo jednym kliknięciem, każdy zapis jest odrzucany, `/api/*` zwraca 404, a `faultline:demo:seed` (uruchamiany co noc) wgrywa trzy syntetyczne projekty z 14 dniami zdarzeń. Na jednym serwerze klon demo dostaje własny `container/.env` z `COMPOSE_PROJECT_NAME=faultline-demo`, `FAULTLINE_IMAGE=faultline-demo` i wolnym `FAULTLINE_PORT`. Nigdy nie podpinaj prawdziwych aplikacji pod instancję demo.

## Testy

```sh
vendor/bin/php-cs-fixer check
vendor/bin/phpstan analyse
vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
php bin/check-coverage.php var/coverage/clover.xml 80
```

Testy czytają `.env.test` i potrzebują PostgreSQL przez `DATABASE_URL` (zmienna środowiskowa albo nieśledzony `.env.test.local`) oraz bazy testowej: `bin/console doctrine:database:create --env=test && bin/console doctrine:migrations:migrate -n --env=test`.

## Licencja

MIT
