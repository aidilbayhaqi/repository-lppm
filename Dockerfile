FROM node:24-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.4-fpm-bookworm AS php-base
RUN apt-get update && apt-get install -y --no-install-recommends libzip-dev libonig-dev libxml2-dev libicu-dev libsqlite3-dev unzip \
    && docker-php-ext-install -j2 pdo_mysql pdo_sqlite mbstring bcmath zip intl pcntl opcache \
    && pecl install redis-6.3.0 && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*
WORKDIR /var/www/html

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-progress --no-scripts --optimize-autoloader
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php artisan package:discover --no-interaction

FROM php-base AS app
COPY --from=dependencies --chown=www-data:www-data /var/www/html /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build public/build
COPY deploy/docker/php.ini /usr/local/etc/php/conf.d/repository.ini
COPY deploy/docker/fpm.conf /usr/local/etc/php-fpm.d/zz-repository.conf
COPY --chmod=755 deploy/docker/entrypoint.sh /usr/local/bin/repository-entrypoint
RUN mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["repository-entrypoint"]
CMD ["php-fpm", "-F"]

FROM php-base AS testing
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-progress --no-scripts
COPY . .
COPY --from=assets /app/public/build public/build
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-scripts && php artisan package:discover --no-interaction \
    && cp .env.example .env && php artisan key:generate --no-interaction
ENV APP_ENV=testing DB_CONNECTION=sqlite DB_DATABASE=:memory: CACHE_STORE=array SESSION_DRIVER=array QUEUE_CONNECTION=sync
CMD ["php", "artisan", "test", "--compact"]

FROM nginx:1.28-alpine AS web
COPY --from=dependencies /var/www/html/public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
COPY deploy/docker/nginx.conf.template /etc/nginx/templates/default.conf.template
