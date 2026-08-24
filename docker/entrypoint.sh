#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set — generating one. Set it as a fixed env var on Render instead once you have it, so it doesn't change on every restart."
    php artisan key:generate --force
fi

php artisan migrate --force

# Stage 55 hotfix — the uploads volume is mounted at storage/app so BOTH
# public logos/signatures and private student photos survive restarts/deploys.
# Keep both directories present before repairing the public symlink.
mkdir -p storage/app/public storage/app/private

# `storage:link` treats an existing path as success even when the path is a
# real directory or a stale/broken symlink. That leaves /public/storage pointing
# at old ephemeral files. Remove any incorrect path first, then recreate the
# link on every boot. MediaController serves files directly from the disks, but
# keeping this link healthy also preserves any legacy /storage URLs.
TARGET="$(readlink -f storage/app/public 2>/dev/null || true)"
LINK_TARGET="$(readlink -f public/storage 2>/dev/null || true)"
if [ -L public/storage ]; then
    if [ -z "$LINK_TARGET" ] || [ "$LINK_TARGET" != "$TARGET" ]; then
        echo "public/storage is a stale or broken symlink -- removing it."
        rm -f public/storage
    fi
elif [ -e public/storage ]; then
    echo "public/storage is a real directory/file instead of the storage symlink -- removing it."
    rm -rf public/storage
fi

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
