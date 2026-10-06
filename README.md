# Faultline

Self-hosted error tracker for my own apps. It speaks the Sentry protocol, so apps keep the official Sentry SDKs and only point the DSN here. Symfony 8, PostgreSQL, Messenger.

[Polska wersja](README.pl.md)

## Development

Configuration comes from environment variables. For local work copy the template, set `APP_ENV=dev`, a random `APP_SECRET` and `DATABASE_URL`:

```sh
cp .env.example .env.local
docker compose up -d
docker compose exec app composer install
docker compose exec app bin/console doctrine:migrations:migrate -n
docker compose exec app bin/console faultline:user:create admin
docker compose exec app bin/console messenger:consume async -vv
```

The app listens on `http://127.0.0.1:8000` (`APP_PORT` changes it).

## Production

On the server fill in every value of `container/.env` (it is read by Compose, never baked into the image), then run the deploy script (build, migrations, `web` + `worker` + `db`):

```sh
cp .env.example container/.env
container/deploy.sh
```

`web` binds to `127.0.0.1:$FAULTLINE_PORT`; put a reverse proxy with TLS in front of it. Migrations run in `deploy.sh`, not on container start. CI deploys pushes to `main` when the `DEPLOY_*` secrets are set.

Daily cron on the host:

```sh
docker compose -f container/compose.yaml exec -T worker php bin/console faultline:purge
```

## Commands

| Command | Purpose |
| --- | --- |
| `faultline:user:create <username>` | Create the admin or reset its password |
| `faultline:project:create <name> [--origin=…] [--retention=30]` | Create a project and print its DSN |
| `faultline:project:rotate-key <slug>` | Replace the key and print the new DSN |
| `faultline:purge` | Delete events past retention and empty issues |
| `faultline:demo:seed` | Wipe the database and load synthetic demo data (only with `FAULTLINE_DEMO=1`) |

## Integrations

- Telegram: set `TELEGRAM_BOT_TOKEN` and `TELEGRAM_CHAT_ID` to get new issues and regressions, at most one message per issue per hour.
- Digest: set `DIGEST_TOKEN` (16+ characters) and call `GET /api/digest` with `Authorization: Bearer <token>`.

## Status bar

`assets/tolemak-bar/tolemak-bar.js` is the shared status bar for all Tolemak apps: a `<tolemak-bar>` element with `<tolemak-field>` children.

- Plain custom elements with no dependencies, so the same file works in React, Twig and static pages.
- Colors come from the host page through `--tb-*` custom properties.
- Styles are constructed stylesheets instead of `<style>` tags, so the bar also works under a strict `style-src` CSP.
- `langList()` is a method, not a getter: React 19 assigns attributes as properties when the element has one of that name.
- Apps with their own theme state cancel the `tolemak-theme` event and apply the theme themselves; otherwise the bar sets `data-theme` and stores the choice in `localStorage`. With storage disabled the choice lasts until reload.
- Language needs the app's own translations, so the bar only announces the choice.

## Demo instance

Set `FAULTLINE_DEMO=1` on a separate instance with its own database. Then `/login` offers a one-click demo login, every write is refused, `/api/*` returns 404 and `faultline:demo:seed` (run it nightly) loads three synthetic projects with 14 days of events. On one host, give the demo clone its own `container/.env` with `COMPOSE_PROJECT_NAME=faultline-demo`, `FAULTLINE_IMAGE=faultline-demo` and a free `FAULTLINE_PORT`. Never point real apps at a demo instance.

## Tests

```sh
vendor/bin/php-cs-fixer check
vendor/bin/phpstan analyse
vendor/bin/phpunit --coverage-clover var/coverage/clover.xml
php bin/check-coverage.php var/coverage/clover.xml 80
```

Tests read `.env.test` and need PostgreSQL through `DATABASE_URL` (environment variable or an untracked `.env.test.local`), plus the test database: `bin/console doctrine:database:create --env=test && bin/console doctrine:migrations:migrate -n --env=test`.

## License

MIT
