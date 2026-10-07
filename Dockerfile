# syntax=docker/dockerfile:1.7
# Persuratan & Layanan FKIP — citra produksi (pola docker-apps): target `app` (php-fpm, Horizon, scheduler) dan `web` (nginx + aset statis).
# Build:  docker compose -f docker-compose.prod.yml build   (ASSET_URL wajib terisi agar aset Vite berawalan /surat/)

ARG PHP_VERSION=8.5

# ---- dasar PHP ---------------------------------------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-bookworm AS base

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libfreetype6-dev libicu-dev libzip-dev libonig-dev libxml2-dev unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql mbstring exif pcntl bcmath gd zip intl \
    # PHP 8.5+ menyertakan opcache bawaan; versi lebih lama memasangnya sebagai ekstensi terpisah
    && (php -m | grep -qi 'zend opcache' || docker-php-ext-install opcache) \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get purge -y --auto-remove && rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-surat.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-surat.conf

WORKDIR /var/www/html

# ---- dependensi Composer (tanpa dev) -------------------------------------------------------------------------------------
FROM base AS vendor
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

# ---- aset front-end ------------------------------------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets
ARG ASSET_URL
ENV ASSET_URL=${ASSET_URL}
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN test -n "$ASSET_URL" || (echo "ASSET_URL wajib diisi (mis. https://supportfkip.unsil.ac.id/surat)" >&2 && exit 1)
RUN npm run build

# ---- aplikasi ------------------------------------------------------------------------------------------------------------
FROM base AS app
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=vendor /var/www/html/vendor ./vendor
COPY . .
COPY --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan filament:upgrade \
    && rm -f /usr/bin/composer \
    && mkdir -p storage/framework/{cache,sessions,views} storage/app/tmp storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/php/entrypoint.sh /usr/local/bin/surat-entrypoint
RUN chmod +x /usr/local/bin/surat-entrypoint

USER www-data
ENTRYPOINT ["surat-entrypoint"]
CMD ["php-fpm"]

# ---- nginx + aset statis ------------------------------------------------------------------------------------------------
FROM nginx:1.27-alpine AS web
COPY docker/nginx/prod.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
