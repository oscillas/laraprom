FROM php:8.4-cli

RUN apt-get update && apt-get upgrade -y \
    && apt-get install -y  \
      libpq-dev \
      libzip-dev \
      git \
      unzip \
    && docker-php-ext-install \
      pdo  \
      pdo_pgsql  \
      zip \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /app