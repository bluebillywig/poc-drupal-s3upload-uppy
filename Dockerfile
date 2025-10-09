FROM drupal:10-apache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Install required PHP extensions and tools
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install Drush globally
RUN composer global require drush/drush:^12 \
    && ln -s /root/.composer/vendor/bin/drush /usr/local/bin/drush

# Set working directory
WORKDIR /var/www/html

# Ensure proper permissions
RUN chown -R www-data:www-data /var/www/html
