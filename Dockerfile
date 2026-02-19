# syntax=docker/dockerfile:1
# Production image for Laravel (PHP-FPM + required extensions).
# Multi-stage: composer → frontend build → final runtime.

# ---------------------------------------------------------------------------
# Stage 1: Composer dependencies
# ---------------------------------------------------------------------------
FROM composer:2.8 AS composer

WORKDIR /app

RUN apk add --no-cache icu-dev && docker-php-ext-install intl

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Stage 2: Frontend assets (Node)
# ---------------------------------------------------------------------------
FROM node:20-alpine AS frontend

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY --from=composer /app /app
COPY vite.config.js ./
COPY resources resources
COPY public public

ENV NODE_ENV=production
ENV VITE_APP_NAME="${VITE_APP_NAME:-Laravel}"
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3: Production runtime (PHP-FPM)
# ---------------------------------------------------------------------------
FROM php:8.4-fpm-bookworm AS runtime

# Install runtime deps and PHP extensions for Laravel + Filament + PostgreSQL + Redis
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq5 \
    libpq-dev \
    libicu72 \
    libicu-dev \
    libzip4 \
    libzip-dev \
    libfreetype6 \
    libfreetype6-dev \
    libjpeg62-turbo \
    libjpeg62-turbo-dev \
    libpng16-16 \
    libpng-dev \
    libwebp7 \
    libwebp-dev \
    libxpm4 \
    libonig5 \
    libxml2 \
    zlib1g-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
    opcache \
    pdo_pgsql \
    intl \
    zip \
    bcmath \
    exif \
    pcntl \
    && pecl install redis && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# PHP production tuning
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini

COPY --from=composer --chown=www-data:www-data /app /var/www/html
COPY --from=frontend --chown=www-data:www-data /app/public/build /var/www/html/public/build

WORKDIR /var/www/html

# Ensure storage, bootstrap/cache and shared public dir exist
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache /shared/public \
    && chown -R www-data:www-data storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
