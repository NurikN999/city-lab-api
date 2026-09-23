FROM composer:2 AS composer

FROM php:8.4-fpm

RUN apt-get update && apt-get install -y \
    libmcrypt-dev \
    git \
    libzip-dev \
    zip \
    libpq-dev \
    libxml2-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libmcrypt-dev \
    libpng-dev \
    libicu-dev \
    locales

RUN sed -i -e \
  's/# ru_RU.UTF-8 UTF-8/ru_RU.UTF-8 UTF-8/' /etc/locale.gen \
   && locale-gen

ENV LANG ru_RU.UTF-8
ENV LANGUAGE ru_RU:ru
ENV LC_LANG ru_RU.UTF-8
ENV LC_ALL ru_RU.UTF-8

COPY --from=composer /usr/bin/composer /usr/bin/composer

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install -j$(nproc) gd

RUN docker-php-ext-install pdo pdo_pgsql soap
RUN apt-get install libsodium-dev -y
RUN docker-php-ext-configure sodium
RUN docker-php-ext-install sodium
RUN docker-php-ext-install zip
RUN docker-php-ext-install pgsql
RUN docker-php-ext-configure intl
RUN docker-php-ext-install intl
