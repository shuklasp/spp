# ==============================================================================
# SPP Framework & Ecosystem Production Dockerfile
# Multi-stage optimized image for PHP 8.2 with SQLite WAL & Apache mod_rewrite
# ==============================================================================

FROM php:8.2-apache

# Set working directory
WORKDIR /var/www/html

# Install system dependencies and build libraries
RUN apt-get update && apt-get install -y --no-install-recommends \
    sqlite3 \
    libsqlite3-dev \
    libzip-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    unzip \
    && docker-php-ext-install \
    pdo_sqlite \
    mbstring \
    curl \
    zip \
    opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

# Configure Apache virtual host
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/000-default.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf \
    && sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Production PHP settings
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=8'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=2'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'memory_limit=256M'; \
    echo 'upload_max_filesize=32M'; \
    echo 'post_max_size=32M'; \
    echo 'date.timezone=UTC'; \
} > /usr/local/etc/php/conf.d/spp-custom.ini

# Copy application files
COPY . /var/www/html/

# Ensure directories exist and permissions are correct
RUN mkdir -p /var/www/html/data /var/www/html/etc /var/www/html/var/cache /var/www/html/var/logs \
    && chown -R www-data:www-data /var/www/html/data /var/www/html/etc /var/www/html/var \
    && chmod -R 775 /var/www/html/data /var/www/html/etc /var/www/html/var

# Copy entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Expose standard HTTP port
EXPOSE 80

# Environment defaults
ENV SPP_PROFILE=lekhak \
    SPP_SEED=true \
    SPP_APP=SPPDocs

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
