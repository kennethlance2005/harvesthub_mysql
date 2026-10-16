FROM php:8.2-apache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Install the required MySQL and multibyte string extensions
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mysqli mbstring \
    && rm -rf /var/lib/apt/lists/*

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

# Before Apache starts, add anything the database is missing compared with
# schema.sql (new tables/columns from teammates). It never deletes data.
CMD ["sh", "-c", "php /var/www/html/tools/sync_schema.php; exec apache2-foreground"]
