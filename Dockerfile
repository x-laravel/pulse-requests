ARG PHP_VERSION=8.3
FROM php:${PHP_VERSION}-cli-bookworm

RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_sqlite

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

WORKDIR /app
