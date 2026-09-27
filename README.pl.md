# Faultline

Własny tracker błędów dla moich aplikacji. Rozumie protokół Sentry, więc aplikacje zostają przy oficjalnych SDK Sentry i zmieniają tylko DSN. Symfony 8, PostgreSQL, Messenger.

[English version](README.md)

## Rozwój

```sh
docker compose up -d
docker compose exec app composer install
docker compose exec app bin/console doctrine:migrations:migrate -n
docker compose exec app bin/console faultline:user:create admin
docker compose exec app bin/console messenger:consume async -vv
```

Aplikacja działa pod `http://127.0.0.1:8000` (port zmienia `APP_PORT`).

## Produkcja

Uzupełnij wszystkie wartości w `container/.env` i uruchom skrypt wdrożenia (build, migracje, `web` + `worker` + `db`):

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
| `faultline:purge` | Usuwa zdarzenia po retencji i puste problemy |

## Integracje

- Telegram: ustaw `TELEGRAM_BOT_TOKEN` i `TELEGRAM_CHAT_ID`, żeby dostawać nowe problemy i regresje, najwyżej jedną wiadomość na problem na godzinę.
- Digest: ustaw `DIGEST_TOKEN` (min. 16 znaków) i wołaj `GET /api/digest` z nagłówkiem `Authorization: Bearer <token>`.

## Testy

```sh
vendor/bin/php-cs-fixer check
vendor/bin/phpstan analyse
vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
php bin/check-coverage.php var/coverage/clover.xml 80
```

Testy potrzebują PostgreSQL (`DATABASE_URL`) i bazy testowej: `bin/console doctrine:database:create --env=test && bin/console doctrine:migrations:migrate -n --env=test`.

## Licencja

MIT
