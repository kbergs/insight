# Use an official PHP base image with the necessary extensions for Drupal
FROM php:8.1-apache

# Set environment variables for the app
ENV DRUPAL_VERSION=11
ENV DRUPAL_DOCROOT=/var/www/html

# Install necessary dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    git \
    unzip \
    mariadb-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql opcache

# Enable Apache modules
RUN a2enmod rewrite

# Copy custom configuration (if needed) or files
COPY ./web/ /var/www/html/
COPY ./install.sql.gz /install.sql.gz

# Make sure the correct permissions are set for the Apache server
RUN chown -R www-data:www-data /var/www/html

# Expose the web server port
EXPOSE 80

# Default command to run Apache in the foreground
CMD ["apache2-foreground"]

# Entry point to import the database on container startup
ENTRYPOINT ["bash", "-c", "if [ -f /install.sql.gz ]; then echo 'Importing database...'; gunzip < /install.sql.gz | mysql -h mariadb -u root -p$MYSQL_ROOT_PASSWORD drupal; fi && apache2-foreground"]
