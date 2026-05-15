FROM node:24-alpine AS assets

WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --prefer-dist \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --no-scripts

FROM php:8.3-fpm-alpine AS app

WORKDIR /var/www/html

RUN apk add --no-cache \
        bash \
        curl \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
        postgresql-dev \
        sqlite-dev \
        zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        zip

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-zolix.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/99-opcache.ini

COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

RUN php artisan package:discover --ansi || true
RUN php artisan vendor:publish --tag=livewire:assets --ansi --force || true

COPY docker/entrypoint.sh /usr/local/bin/zolix-entrypoint
RUN chmod +x /usr/local/bin/zolix-entrypoint \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache public

USER www-data

ENTRYPOINT ["zolix-entrypoint"]
CMD ["php-fpm"]

FROM nginx:1.29-alpine AS web

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public
