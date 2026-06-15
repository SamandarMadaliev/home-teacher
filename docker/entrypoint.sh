#!/bin/sh
set -e

cd /var/www/html

mkdir -p storage/framework/sessions \
         storage/framework/views \
         storage/framework/cache/data \
         storage/app/public \
         storage/logs \
         bootstrap/cache

chmod -R 775 storage bootstrap/cache

# Kubernetes / Docker Compose: mirror injected env into .env for PHP-FPM + Laravel.
# Do not copy .env.example when the orchestrator already provides APP_KEY.
if [ -n "$APP_KEY" ]; then
    php docker/sync-dotenv.php
elif [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force --no-interaction
fi

# SQLite — use K8s path when set (e.g. /data/database.sqlite)
if [ -n "$DB_DATABASE" ]; then
    mkdir -p "$(dirname "$DB_DATABASE")"
    if [ ! -f "$DB_DATABASE" ]; then
        touch "$DB_DATABASE"
    fi
    chmod 664 "$DB_DATABASE" 2>/dev/null || true
elif [ ! -f database/database.sqlite ]; then
    touch database/database.sqlite
    chmod 664 database/database.sqlite
fi

php artisan migrate --force --no-interaction

php artisan storage:link --force --no-interaction 2>/dev/null || true

# Never cache config with stale http:// values from the image build.
php artisan config:clear --no-interaction
php artisan route:clear --no-interaction
php artisan view:clear --no-interaction

# Rebuild caches from runtime env (APP_URL=https://… from ConfigMap).
if [ -n "$APP_URL" ]; then
    php artisan config:cache --no-interaction
    php artisan route:cache --no-interaction
fi

exec "$@"
