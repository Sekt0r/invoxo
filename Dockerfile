FROM php:8.2-cli-bullseye

ENV DEBIAN_FRONTEND=noninteractive
ENV LANG=en_US.UTF-8

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

# Node + npm (keep your approach, or switch to node:<ver>-slim if you want determinism)
COPY --from=node:latest /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node:latest /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN apt-get update \
 && apt-get install -y -q \
    libpq-dev zlib1g-dev libxml2-dev wget git libzip-dev mc \
 && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) pgsql pdo_pgsql opcache xml soap zip pcntl

# OpenSwoole (Octane uses `--server=swoole` even when the extension is openswoole)
RUN pecl install openswoole \
 && docker-php-ext-enable openswoole

WORKDIR /app

# Entry point does runtime bootstrap for bind-mounted code
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 8888
ENTRYPOINT ["entrypoint"]
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8888"]
