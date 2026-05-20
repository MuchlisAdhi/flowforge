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

# Install system dependencies and PHP extensions
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    linux-headers \
    && docker-php-ext-install pdo pdo_pgsql pcntl zip opcache

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

WORKDIR /app

# Copy vendor from build stage
COPY --from=vendor /app/vendor ./vendor

# Copy application code
COPY . .

# Generate optimized autoload (composer available from COPY)
RUN cp /usr/bin/composer /usr/local/bin/composer 2>/dev/null || true
RUN if command -v composer > /dev/null; then composer dump-autoload --optimize --no-dev; fi

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
