FROM php:7.4-apache

RUN apt-get update && \
    apt-get install -y libpq-dev && \
    apt-get clean && \
    docker-php-ext-install pgsql && \
    cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini

WORKDIR /var/www/html

COPY . /var/www/html

EXPOSE 80
