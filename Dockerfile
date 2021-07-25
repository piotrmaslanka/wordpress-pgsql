FROM php:8.0-apache

WORKDIR /var/www/html

COPY . /var/www/html

EXPOSE 80
