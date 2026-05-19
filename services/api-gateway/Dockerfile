# ============================================
# Stage 1: Dependencies
# ============================================
FROM composer:2 AS vendor

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# ============================================
# Stage 2: Application
# ============================================
FROM php:8.3-fpm-alpine AS app

# Install system dependencies
RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    nginx \
    && docker-php-ext-install pdo pdo_pgsql pcntl zip opcache sockets

# Install Redis extension
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# PHP configuration
COPY docker/php.ini /usr/local/etc/php/conf.d/custom.ini

WORKDIR /app

# Copy vendor from build stage
COPY --from=vendor /app/vendor ./vendor

# Copy application code
COPY . .

# Generate optimized autoload
RUN composer dump-autoload --optimize --no-dev

# Set permissions
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Copy supervisor config
COPY docker/supervisord.conf /etc/supervisord.conf

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
