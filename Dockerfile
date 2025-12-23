# Use PHP 8.1 with Apache as base image
FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Install PHP dependencies if composer.json exists in app/
RUN if [ -f app/composer.json ]; then cd app && composer install --no-dev --optimize-autoloader; fi

# Install Node.js dependencies
RUN npm install

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port 80 for Apache
EXPOSE 80

# Copy configuration files
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY healthcheck.sh /usr/local/bin/healthcheck.sh
COPY backup.sh /usr/local/bin/backup.sh
COPY migration.sh /usr/local/bin/migration.sh

# Set executable permissions
RUN chmod +x /usr/local/bin/healthcheck.sh \
    && chmod +x /usr/local/bin/backup.sh \
    && chmod +x /usr/local/bin/migration.sh

# Create necessary directories and set permissions
RUN mkdir -p /var/log/supervisor /var/log/backup /var/log/migration /var/www/html/backups /var/www/html/migrations \
    && chown -R www-data:www-data /var/www/html/backups /var/www/html/migrations

# Add health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD /usr/local/bin/healthcheck.sh

# Run migrations on startup
RUN /usr/local/bin/migration.sh || true

# Start supervisord
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
