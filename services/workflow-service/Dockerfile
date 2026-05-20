# ============================================
# Stage 1: Dependencies
# ============================================
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --ignore-platform-reqs

# ============================================
# Stage 2: Application
# ============================================
FROM php:8.3-cli-alpine AS app

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    linux-headers \
    && docker-php-ext-install pdo pdo_pgsql pcntl zip opcache

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /app

COPY --from=vendor /app/vendor ./vendor
COPY . .

EXPOSE 8002

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8002"]
