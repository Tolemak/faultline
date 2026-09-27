FROM php:8.4-cli-alpine

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN install-php-extensions pdo_pgsql intl zip pcov opcache

WORKDIR /app

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]
