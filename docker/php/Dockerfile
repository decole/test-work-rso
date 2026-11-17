FROM php:8.4-fpm

ARG USER_ID=1000
ARG GROUP_ID=1000

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libicu-dev \
        libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        intl \
        opcache \
        zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

COPY docker/php/php.ini /usr/local/etc/php/conf.d/zz-symfony.ini

RUN groupmod -o -g "${GROUP_ID}" www-data \
    && usermod -o -u "${USER_ID}" www-data \
    && mkdir -p /var/www/.composer /var/www/.cache \
    && chown -R www-data:www-data /var/www

WORKDIR /var/www/html

USER www-data

CMD ["php-fpm"]
