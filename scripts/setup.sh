#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."

[ -f .env ] || cp .env.example .env
docker compose build app
if grep -q '^APP_KEY=$' .env; then
    task_key=$(docker compose run --rm --no-deps app php artisan key:generate --show)
    case "$task_key" in base64:*) ;; *) echo 'Unable to generate APP_KEY' >&2; exit 1 ;; esac
    awk -v key="$task_key" '/^APP_KEY=/ {$0="APP_KEY=" key} {print}' .env > .env.setup.tmp
    mv .env.setup.tmp .env
fi
docker compose run --rm app php artisan migrate --force
docker compose up -d --wait app worker scheduler
echo 'API is ready. The following local demo token expires in seven days:'
docker compose run --rm --no-deps app php artisan demo:token
