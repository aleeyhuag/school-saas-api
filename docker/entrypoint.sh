#!/bin/sh
set -e

echo "EduVentor: starting application bootstrap..."

if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set — generating one. Set it as a fixed env var on Render instead once you have it, so it doesn't change on every restart."
    php artisan key:generate --force
fi

echo "EduVentor: running database migrations..."
php artisan migrate --force

# One-time production Super Admin bootstrap.
#
# Set these THREE variables temporarily in Render:
#   SUPERADMIN_NAME
#   SUPERADMIN_EMAIL
#   SUPERADMIN_PASSWORD
#
# The command is idempotent:
# - existing Super Admin with the same email -> succeeds without changes
# - existing non-Super-Admin with the same email -> fails safely
# - missing account -> creates the Super Admin
#
# IMPORTANT:
# Remove these three variables from Render after the first successful
# deployment/login. The account remains in the database.
if [ -n "$SUPERADMIN_NAME" ] || [ -n "$SUPERADMIN_EMAIL" ] || [ -n "$SUPERADMIN_PASSWORD" ]; then
    if [ -n "$SUPERADMIN_NAME" ] && [ -n "$SUPERADMIN_EMAIL" ] && [ -n "$SUPERADMIN_PASSWORD" ]; then
        echo "EduVentor: Super Admin bootstrap variables detected."
        php artisan make:super-admin \
            --bootstrap \
            --name="$SUPERADMIN_NAME" \
            --email="$SUPERADMIN_EMAIL" \
            --password="$SUPERADMIN_PASSWORD"
    else
        echo "ERROR: Super Admin bootstrap requires SUPERADMIN_NAME, SUPERADMIN_EMAIL and SUPERADMIN_PASSWORD to all be set."
        exit 1
    fi
fi

php artisan storage:link || true
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "EduVentor: application bootstrap complete."

exec "$@"
