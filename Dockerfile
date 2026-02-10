# Use official PHP-Apache image
FROM php:8.2-apache

# Set environment variables
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    unzip \
    curl \
    git \
    && docker-php-ext-install pdo pdo_sqlite zip curl mbstring xml bcmath intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update Apache configuration
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set working directory
WORKDIR /var/www/html

# Copy project files
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
