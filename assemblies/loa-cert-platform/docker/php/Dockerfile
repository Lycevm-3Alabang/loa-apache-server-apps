FROM composer:2 AS composer

FROM php:8.3-fpm

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        unzip \
        curl \
        libzip-dev \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libgd-dev \
        libxml2-dev \
        libicu-dev \
        libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql bcmath zip gd mbstring intl \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /var/www/html/storage/framework/{cache,sessions,views} \
    && mkdir -p /var/www/html/storage/logs \
    && mkdir -p /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Increase PHP body/upload limits for template editor (base64 images in HTML/CSS)
RUN echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini
