# SACCO system - PHP-FPM image.  Build from the project root:
#   docker compose -f deploy/docker-compose.yml up -d --build
FROM php:8.3-fpm-alpine

RUN apk add --no-cache icu-dev libzip-dev libpng-dev libjpeg-turbo-dev freetype-dev oniguruma-dev mysql-client \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) pdo_mysql mbstring zip gd intl bcmath opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY deploy/php.ini /usr/local/etc/php/conf.d/sacco.ini

WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --optimize-autoloader --no-interaction \
 && chown -R www-data:www-data storage bootstrap/cache

USER www-data
CMD ["php-fpm"]
