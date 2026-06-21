FROM php:8.2-apache

# Install system deps + PHP extensions
RUN docker-php-ext-install pdo pdo_mysql && a2enmod rewrite

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache should serve from /public
RUN sed -ri -e 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# Copy composer files first (better layer caching)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Copy the rest of the app
COPY . .

# Railway provides $PORT; Apache needs to listen on it
RUN echo "Listen \${PORT:-80}" > /etc/apache2/ports.conf
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

EXPOSE 8080
