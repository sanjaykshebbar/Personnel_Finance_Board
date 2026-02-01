# Use official PHP-Apache image (Bullseye for better stability/compat)
FROM php:8.2-apache-bullseye

# Set environment variables
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Update and install system dependencies, then clean up in one layer
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo pdo_sqlite zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache configuration to allow .htaccess and set DocumentRoot
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files (respecting .dockerignore)
COPY . /var/www/html/

# Create necessary directories and set permissions for Apache
RUN mkdir -p /var/www/html/db /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/ \
    && chmod -R 770 /var/www/html/db /var/www/html/uploads

# Set volumes for data persistence
VOLUME ["/var/www/html/db", "/var/www/html/uploads"]

# Expose port 80
EXPOSE 80

# Default command
CMD ["apache2-foreground"]
