#!/bin/sh
set -eu

cd "$(dirname "$0")"

git pull --ff-only
docker compose build web
docker compose up -d db
docker compose run --rm --no-deps web php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
docker compose up -d --remove-orphans
docker image prune -f
