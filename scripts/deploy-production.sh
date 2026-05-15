#!/usr/bin/env bash
set -euo pipefail

php artisan down --render="errors::503" || true
trap 'php artisan up || true' EXIT

composer install --no-dev --optimize-autoloader
npm ci
npm run build

rm -f public/hot

php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
php artisan app:production-check --strict

php artisan up
trap - EXIT
