FROM php:8.4-fpm-alpine
# Dependances systeme
RUN apk add --no-cache postgresql-dev git unzip icu-dev \
    && docker-php-ext-install pdo pdo_pgsql bcmath intl
# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
EXPOSE 9000
CMD ["php-fpm"]
