FROM php:8.2-apache

# Install the required MySQL PDO extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copy the HarvestHub project files into the container's web directory
COPY . /var/www/html/

# Update Apache configuration to listen on Render's dynamic PORT environment variable
RUN sed -i "s/80/\${PORT}/g" /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Set a default port fallback and expose it
ENV PORT=10000
EXPOSE ${PORT}