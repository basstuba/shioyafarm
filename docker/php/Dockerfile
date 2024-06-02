FROM php:8.2.19-fpm

COPY php.ini /usr/local/etc/php/

RUN apt-get update \
    && apt-get install -y \
    default-mysql-client \
    zlib1g-dev \
    libzip-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    unzip \
    libmagickwand-dev --no-install-recommends \
    && docker-php-ext-install pdo_mysql zip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && pecl install imagick \
    && docker-php-ext-enable imagick \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php \
    && mv composer.phar /usr/local/bin/composer \
    && composer self-update

WORKDIR /var/www