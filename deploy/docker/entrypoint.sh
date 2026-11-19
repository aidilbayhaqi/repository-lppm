#!/bin/sh
set -eu
umask 027
if [ -z "${APP_KEY:-}" ]; then
    echo "APP_KEY belum diisi. Lengkapi file environment sebelum menjalankan container." >&2
    exit 1
fi
mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private bootstrap/cache
# Every replica receives the same environment and APP_KEY. Never generate keys or run migrations here.
php artisan config:cache
php artisan route:cache
php artisan view:cache
exec "$@"
