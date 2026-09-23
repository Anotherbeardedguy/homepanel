FROM php:8.5-fpm-bookworm

ARG UID=1000
ARG GID=1000

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev libsqlite3-dev libicu-dev unzip \
    && docker-php-ext-install pdo_pgsql pdo_sqlite bcmath intl zip pcntl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* \
    && usermod -o -u ${UID} www-data \
    && groupmod -o -g ${GID} www-data

COPY docker/php/php.ini /usr/local/etc/php/conf.d/homepanel.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/zz-homepanel.conf

WORKDIR /var/www/html
