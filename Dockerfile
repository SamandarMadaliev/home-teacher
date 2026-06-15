FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    sqlite \
    sqlite-dev \
    nodejs \
    npm \
    python3 \
    go \
    nginx \
    supervisor \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_sqlite \
    mbstring \
    xml \
    zip \
    opcache

# PHP-FPM must inherit container env (Kubernetes ConfigMap / Secrets).
# Default clear_env=yes strips APP_URL etc. from web requests.
COPY docker/php-fpm-laravel.conf /usr/local/etc/php-fpm.d/zzz-laravel.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .

RUN mkdir -p bootstrap/cache storage/app/public storage/framework/cache \
        storage/framework/sessions storage/framework/views storage/logs && \
    chmod -R 775 bootstrap/cache storage

RUN composer dump-autoload --optimize --no-dev

RUN npm ci --ignore-scripts && npm run build && rm -rf node_modules

RUN touch database/database.sqlite && chmod 664 database/database.sqlite

COPY docker/nginx-proxy.conf /etc/nginx/http.d/00-proxy.conf
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]
