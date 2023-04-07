# syntax=docker/dockerfile:1.4

## Installing composer packages
########
FROM composer:2.4.3 as vendor

WORKDIR /var/www/html
## Main stage with everything included
########
FROM php:8.1.12-fpm-buster


RUN apt-get update -yqq && \
    apt-get install -yqq \
      libmemcached-dev \
      libevent-dev \
      zlib1g-dev \
      wget \
      libcurl4-openssl-dev \
      unzip \
      libzip-dev \
      libgmp-dev \
      zlib1g-dev \
      libpng-dev \
      procps && \
      apt-get clean && \
      pecl install \
      memcached-3.2.0 \
      xdebug && \
      docker-php-ext-enable \
      memcached \
      xdebug

RUN docker-php-ext-install pdo_mysql && \
    docker-php-ext-install bcmath    && \
    docker-php-ext-install gmp       && \
    docker-php-ext-install gd        && \
    docker-php-ext-install zip       && \
    docker-php-ext-install opcache

RUN pecl install datadog_trace && \
    docker-php-ext-enable ddtrace

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY php.dev.ini /usr/local/etc/php/php.ini
COPY ./docker.conf /usr/local/etc/php-fpm.d/zzz-docker.conf
COPY opcache.dev.ini /usr/local/etc/php/conf.d/opcache.ini
COPY xdebug.dev.ini /xdebug/xdebug.ini
COPY xdebug.dev.ini /xdebug/xdebug.ini
COPY composer.json /var/www/html/composer.json
COPY composer.lock /var/www/html/composer.lock

RUN touch /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

WORKDIR /var/www/html

RUN composer install

RUN chown -R 1000.1000 /var/www/html

RUN ln -s /var/www/html/vendor/bin/stest /usr/bin && ln -s /var/www/html/vendor/bin/stest-all /usr/bin

CMD ["php-fpm", "-R"]
