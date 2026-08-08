FROM php:8.3-fpm-alpine

# Installer les extensions PostgreSQL pour PHP
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql

WORKDIR /var/www/html

EXPOSE 9000

CMD ["php-fpm"]
