FROM php:8.2-apache

# Install system dependencies needed by Composer and PHP extensions
RUN apt-get update && apt-get install -y \
    unzip \
    zip \
    libzip-dev \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

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

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

EXPOSE 8080

# Fix Railway's MPM conflict at container startup (not build time),
# set Apache to listen on Railway's $PORT, then launch Apache
CMD ["bash", "-lc", "\
    set -e; \
    a2dismod mpm_event mpm_worker > /dev/null 2>&1 || true; \
    rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* || true; \
    a2enmod mpm_prefork > /dev/null 2>&1 || true; \
    echo \"Listen ${PORT:-80}\" > /etc/apache2/ports.conf; \
    exec apache2-foreground \
"]