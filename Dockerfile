FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends cron libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql \
    && php -r 'exit(extension_loaded("openssl") && extension_loaded("mbstring") ? 0 : 1);' \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --no-progress --no-autoloader
COPY . .
RUN COMPOSER_ALLOW_SUPERUSER=1 composer dump-autoload --no-dev --optimize

COPY docker/php/fpm.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY --chmod=0644 docker/cron/sessions /etc/cron.d/kanban-sessions
