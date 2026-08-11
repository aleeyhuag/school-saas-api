#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set — generating one. Set it as a fixed env var on Render instead once you have it, so it doesn't change on every restart."
    php artisan key:generate --force
fi

php artisan migrate --force
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
