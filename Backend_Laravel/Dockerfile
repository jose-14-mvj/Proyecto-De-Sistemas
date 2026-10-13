# MigraSense · Backend Laravel para Render
FROM php:8.2-apache

# Extensiones de PHP que usa la app (MySQL, zip para Composer)
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev \
 && docker-php-ext-install pdo_mysql zip \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Apache sirve solo la carpeta public/ (el código y el .env quedan fuera del alcance web)
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
RUN sed -ri 's/ServerTokens OS/ServerTokens Prod/; s/ServerSignature On/ServerSignature Off/' /etc/apache2/conf-available/security.conf \
 && printf "expose_php = Off\nzend.exception_ignore_args = On\ndisplay_errors = Off\nmax_execution_time = 150\n" > /usr/local/etc/php/conf.d/seguridad.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# Dependencias primero (se reutilizan entre despliegues si no cambian)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize --no-dev \
 && php artisan package:discover --ansi \
 && chown -R www-data:www-data storage bootstrap/cache

COPY docker/inicio.sh /usr/local/bin/inicio.sh
# sed: por si Git en Windows le puso saltos de línea CRLF (Linux no lo ejecutaría)
RUN sed -i 's/\r$//' /usr/local/bin/inicio.sh /etc/apache2/sites-available/000-default.conf \
 && chmod +x /usr/local/bin/inicio.sh

# Render indica el puerto en PORT
ENV PORT=10000
EXPOSE 10000
CMD ["/usr/local/bin/inicio.sh"]
