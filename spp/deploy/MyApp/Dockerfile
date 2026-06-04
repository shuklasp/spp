FROM php:8.2-fpm-alpine

# Install essential extensions for SPP
RUN apk add --no-cache \
    $PHPIZE_DEPS \
    linux-headers \
    nginx \
    supervisor \
    mariadb-client \
    sqlite-dev \
    && docker-php-ext-install pdo pdo_mysql pdo_sqlite opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

WORKDIR /var/www/html

# Copy configuration
COPY ./deploy/MyApp/nginx.conf /etc/nginx/nginx.conf
COPY ./deploy/MyApp/supervisord.conf /etc/supervisord.conf

# Copy application source
COPY . /var/www/html/

# Expose Web Port
EXPOSE 80

# SPPMigrate - Automatically migrate on startup
# We run migration directly via CLI before starting the main process
CMD ["/bin/sh", "-c", "php spp.php sppmigrate:run && /usr/bin/supervisord -c /etc/supervisord.conf"]