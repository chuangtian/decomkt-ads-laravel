FROM php:8.4-fpm-alpine

RUN apk add --no-cache $PHPIZE_DEPS git unzip icu-dev libzip-dev linux-headers nodejs npm \
    && docker-php-ext-install intl pcntl pdo_mysql zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
