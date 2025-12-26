FROM php:8.2-cli-bullseye

ENV DEBIAN_FRONTEND noninteractive
ENV LANG en_US.UTF-8

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

COPY --from=node:latest /usr/local/lib/node_modules /usr/local/lib/node_modules
COPY --from=node:latest /usr/local/bin/node /usr/local/bin/node
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini

RUN apt-get update && \
    apt-get install -y -q libpq-dev zlib1g-dev libxml2-dev wget git libzip-dev mc && \
    rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install -j$(nproc) pgsql pdo_pgsql opcache xml soap zip pcntl

RUN pecl install openswoole && docker-php-ext-enable openswoole

# Install PHP extensions
#RUN docker-php-ext-install -j$(nproc) \
#    pdo_pgsql \
#    pgsql \
#    intl \
#    zip \
#    pcntl \
#    opcache \
#    soap

# Install Swoole
#RUN pecl install swoole && \
#    docker-php-ext-enable swoole && \
#    php -m | grep -i swoole

# Set working directory
WORKDIR /app

# Copy application code (for prod builds; overridden by bind mount in dev)
COPY . .

# Run composer install again to ensure autoloader is generated
# Skip scripts to avoid requiring .env file during build
# In dev, this will be overridden by bind mount, but keep for prod builds
RUN composer install --no-interaction --prefer-dist --no-scripts --no-dev || true

# Ensure storage and cache directories are writable
# In dev bind-mount, host permissions will apply, but this helps for prod
RUN mkdir -p storage/logs storage/framework/cache storage/framework/sessions storage/framework/views \
    bootstrap/cache && \
    chmod -R 777 storage bootstrap/cache || true

# Expose port 8000 for Octane
EXPOSE 8888

# Default command (override in docker-compose)
CMD ["php", "artisan", "octane:start", "--server=swoole", "--host=0.0.0.0", "--port=8888"]

