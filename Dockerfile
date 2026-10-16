FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install the required MySQL PDO extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN apt-get update \
    && apt-get install -y --no-install-recommends unzip \
    && rm -rf /var/lib/apt/lists/* \
    && composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Copy the HarvestHub project files into the container's web directory
COPY . /var/www/html/

# Update Apache configuration to listen on Render's dynamic PORT environment variable
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Set a default port fallback and expose it
ENV PORT=10000
EXPOSE ${PORT}
