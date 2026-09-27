FROM php:8.4-apache AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions pdo_pgsql intl zip opcache \
    && a2enmod headers expires \
    && rm -rf /var/www/html

COPY container/php/production.ini "$PHP_INI_DIR/conf.d/zz-faultline.ini"
COPY container/apache/faultline.conf /etc/apache2/sites-available/000-default.conf
COPY container/apache/limits.conf /etc/apache2/conf-enabled/zz-limits.conf

WORKDIR /app

FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV APP_ENV=prod \
    APP_DEBUG=0 \
    COMPOSER_ALLOW_SUPERUSER=1

COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-progress

COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative \
    && php bin/console importmap:install \
    && php bin/console asset-map:compile \
    && php bin/console cache:warmup

FROM base AS runtime

ENV APP_ENV=prod \
    APP_DEBUG=0

COPY --from=build --chown=www-data:www-data /app /app
