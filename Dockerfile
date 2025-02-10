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
      zip

# Install Composer
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" \
    && php -r "if (hash_file('sha384', 'composer-setup.php') === 'dac665fdc30fdd8ec78b38b9800061b4150413ff2e3b6f88543c636f7cd84f6db9189d43a81e5503cda447da73c7e5b6') { echo 'Installer verified'.PHP_EOL; } else { echo 'Installer corrupt'.PHP_EOL; unlink('composer-setup.php'); exit(1); }" \
    && php composer-setup.php \
    && mv composer.phar /usr/local/bin/composer

WORKDIR /app