# Faultline plan

Self-hosted error tracker that speaks the Sentry protocol, so apps keep using the official SDKs and only change the DSN. One admin, a handful of projects, low traffic. It has to fit next to three other containers on a 1 GiB VPS.

## Stack

- PHP 8.4, Symfony 8.1, Doctrine ORM + Migrations, PostgreSQL 18
- Messenger with the Doctrine transport (no Redis)
- Twig + Symfony UX Turbo/Stimulus, AssetMapper (no Node build)
- PHPUnit, PHPStan level 8, PHP-CS-Fixer (`@Symfony`), coverage gate 80%
- Docker Compose: `web` (Apache + PHP), `worker` (same image, `messenger:consume`), `db`

## Sentry protocol scope

- DSN: `https://<public_key>@<host>/<project_id>`
- `POST /api/{projectId}/envelope/` and `POST /api/{projectId}/store/`
- Auth from `X-Sentry-Auth` (`sentry_key=`), the `sentry_key` query param, or the envelope header `dsn`. Wrong key → 401, unknown project → 404.
- Envelope: header line, then items (item header + payload, `length` optional). Only `event` items are stored. `transaction`, `session`, `client_report`, `attachment` etc. are accepted and dropped, so SDKs never retry.
- `Content-Encoding`: gzip, deflate, none. Body limit 1 MiB on the wire, 8 MiB after inflating, checked while inflating (no zip bombs). JSON depth limit.
- Response `{"id": "<event_id>"}`. Rate limited per project key → 429 + `Retry-After` + `X-Sentry-Rate-Limits`.
- CORS for browser SDKs: allowed origins per project, preflight handled.

Contract tests run the official SDKs' serializers against the endpoints: `sentry/sentry` in PHPUnit (transport captured, then posted to the kernel), plus recorded synthetic payloads from `@sentry/node`, `@sentry/browser` and `sentry-sdk` (Python) as fixtures.

## Domain

- `Project`: name, slug, public key (rotatable), allowed origins, retention days (default 30).
- `Issue`: project, fingerprint hash, title, culprit, level, status (`unresolved`, `resolved`, `ignored`), first/last seen, event count, last release.
- `Event`: issue, event id (uuid, unique per project), received at, level, environment, release, platform, message, exception + stack trace, tags, contexts, request (all JSONB, scrubbed).
- `IssueDailyCount`: issue, day, count. Feeds sparklines without scanning events.
- `User`: single admin, created from the console.

Ingest only validates, authenticates and dispatches. The worker normalizes, scrubs, groups, persists and notifies. Duplicate event ids are ignored.

## Grouping

1. `fingerprint` from the event, with `{{ default }}` expanded.
2. Exception chain: type + in-app frames (module/function or file/function, no line numbers). Falls back to all frames when none are in-app.
3. Message template (`logentry.message`, else `message`) with numbers, uuids, hex and quoted strings replaced.

SHA-256 of the parts → `Issue`. A new event on a resolved issue reopens it as a regression.

## Scrubbing

Before anything is stored: keys matching `password|passwd|secret|token|api_?key|auth|cookie|session|csrf|dsn|private` are replaced with `[filtered]` at any depth, `Authorization` and `Cookie` headers dropped, card numbers (Luhn) and IBANs masked in strings, `user.ip_address` dropped. Query strings go through the same key filter.

## UI

- Login (throttled, CSRF), then projects list with 24h sparkline and open issue counts.
- Issues per project: filters (status, level, environment, release), search in title/culprit, sort by last seen / events / first seen. Paginated.
- Issue detail: stack trace with in-app frames highlighted and others collapsed, tags breakdown, contexts, request, event pager, resolve / ignore / reopen via Turbo.
- Project settings: DSN, key rotation, allowed origins, retention, setup snippets for PHP/Symfony, Node, browser and Python.
- PL/EN everywhere (Symfony Translation, `tolemak-lang` event), light/dark via `tolemak-bar` (`assets/tolemak-bar/tolemak-bar.js`, keep it byte-identical with the other apps).
- Look: a seismograph. Event rate drawn as a recorder trace, levels as magnitudes, graph-paper grid in light mode, dark phosphor in dark mode. Own character, same bottom bar as the rest of the family.
- Strict CSP (no inline scripts or styles; `tolemak-bar` already uses constructed sheets). Phone width works.

## Operations

- `faultline:user:create`, `faultline:project:create`, `faultline:project:rotate-key`
- `faultline:purge`: deletes events past retention and issues left without events. Cron daily.
- Telegram notifier: new issue and regression, one message per issue per hour at most, token and chat id from env. Disabled when unset.
- `GET /api/digest` behind a bearer token from env: new issues, regressions and top issues from the last 24h as JSON. The home assistant bot reads it for its morning report.
- Postgres tuned for a small box (`shared_buffers=32MB`, `max_connections=20`, `work_mem=2MB`). Memory limits: web 160M, worker 96M, db 128M. Worker restarts with `--memory-limit=64M --time-limit=3600`.
- Port `127.0.0.1:PORT`, Apache on the host proxies to it. Nothing about the server (host, port, paths) goes into the repo.

## Security checklist

Untrusted input everywhere on ingest: size and depth limits, strict types when mapping payloads, no `unserialize`, no SSRF (the server never fetches URLs from events), Twig autoescape only (no `|raw` on event data), CSP, CSRF on every form, login throttling, `composer audit` clean in CI, secrets only in env.

## Phases

Each phase is one branch and ends green: CS, PHPStan, tests, coverage ≥ 80%, `composer audit`.

1. **Skeleton**: Symfony skeleton + packages from the stack, Docker Compose for dev, CI (no deploy), PHPStan, CS fixer, coverage script, Dependabot (monthly, grouped), `.env.example`.
2. **Ingest**: entities + migrations, DSN auth, both endpoints, decompression guard, rate limiting, CORS, Messenger pipeline, grouping, scrubbing, contract tests.
3. **UI**: login, projects, issues, issue detail, actions, settings, snippets, i18n, theme, look, CSP.
4. **Operations**: console commands, purge, Telegram, digest API, production Docker with limits, deploy step in CI (secrets only), README.
5. **Server + integrations** (outside this repo): deploy target on the VPS, vhost, DNS, certificate, then the DSN in CryptoPulse, File Actions, mathema, the tracker and the home dashboard.
