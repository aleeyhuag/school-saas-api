#!/bin/sh
set -e

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set — generating one. Set it as a fixed env var on Render instead once you have it, so it doesn't change on every restart."
    php artisan key:generate --force
fi

php artisan migrate --force

# Stage 55 hotfix: `storage:link` treats "the path already exists" as
# success and leaves it untouched -- it does NOT verify that path is
# actually a symlink pointing at the right target. If `public/storage`
# is ever a real directory instead of a live symlink (a stale manual
# copy, an old deploy artifact, anything), every future boot's
# storage:link call silently no-ops forever and newly uploaded
# logos/signatures/photos never become reachable at their public URL
# again, even though the underlying files are safely persisted on the
# mounted disk. Confirmed present on disk during this hotfix: a
# `public/storage` that was a real directory, dated days behind
# `storage/app/public`, missing a since-uploaded folder entirely.
# Self-heal every boot: if the link exists but is not a correct
# symlink to storage/app/public, remove it first so storage:link
# always recreates it properly.
TARGET="$(readlink -f storage/app/public 2>/dev/null || true)"
LINK_TARGET="$(readlink -f public/storage 2>/dev/null || true)"
if [ -e public/storage ] && [ "$LINK_TARGET" != "$TARGET" ]; then
    echo "public/storage is not a valid symlink to storage/app/public -- removing and recreating it."
    rm -rf public/storage
fi
php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
