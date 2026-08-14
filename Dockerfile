# Production image for Render
FROM php:8.4-apache

# PHP extensions required by Skulag
RUN apt-get update && apt-get install -y \
        libzip-dev \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        unzip \
        git \
    && docker-php-ext-install pdo_pgsql zip gd mbstring xml bcmath \
    && a2enmod rewrite \
    && a2enmod headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Laravel must be served from public/
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/apache2.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' \
        /etc/apache2/apache2.conf

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --optimize-autoloader

COPY . .

RUN composer dump-autoload --optimize --no-dev

RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]

CMD ["apache2-foreground"]