FROM php:8.0-apache

RUN apt-get update && \
    apt-get install -y libpq-dev && \
    apt-get clean

RUN docker-php-ext-install pgsql

WORKDIR /var/www/html

COPY . /var/www/html

EXPOSE 80
