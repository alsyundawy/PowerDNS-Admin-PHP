# ==============================================================================
# PowerDNS-Admin-PHP — Enterprise Production Dockerfile
# Native PHP 8.3-FPM Minimal Alpine Linux Container (Zero External Frameworks)
# ==============================================================================

FROM php:8.3-fpm-alpine

# Metadata labels
LABEL maintainer="Harry Dertin Sutisna Alsyundawy <https://github.com/alsyundawy>"
LABEL org.opencontainers.image.title="PowerDNS-Admin-PHP"
LABEL org.opencontainers.image.description="Enterprise Authoritative PowerDNS Web Control Plane in Native PHP (Zero-Framework, Air-Gapped Zero-CDN, 2FA TOTP & Clustering)"
LABEL org.opencontainers.image.version="0.3.0"
LABEL org.opencontainers.image.licenses="MIT"

# Set working directory
WORKDIR /var/www/html

# Install packages, compile extensions, configure PHP, and prepare directories in a single layer
RUN apk add --no-cache \
        bash \
        curl \
        fcgi \
        mariadb-client \
        libzip \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        curl-dev \
        linux-headers \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        curl \
    && pecl install apcu \
    && docker-php-ext-enable apcu opcache \
    && apk del .build-deps \
    && rm -rf /tmp/pear \
    && { \
        echo 'opcache.enable=1'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=2'; \
        echo 'opcache.validate_timestamps=1'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.fast_shutdown=1'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini \
    && { \
        echo 'memory_limit = 256M'; \
        echo 'upload_max_filesize = 32M'; \
        echo 'post_max_size = 32M'; \
        echo 'max_execution_time = 60'; \
        echo 'date.timezone = UTC'; \
        echo 'display_errors = Off'; \
        echo 'log_errors = On'; \
    } > /usr/local/etc/php/conf.d/custom-php.ini \
    && mkdir -p /var/www/html/public/assets/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html/public/assets/uploads

# Copy application directories explicitly to guarantee zero leakage of build or development files
COPY --chown=www-data:www-data app/ /var/www/html/app/
COPY --chown=www-data:www-data public/ /var/www/html/public/
COPY --chown=www-data:www-data views/ /var/www/html/views/
COPY --chown=www-data:www-data sql/ /var/www/html/sql/
COPY --chown=www-data:www-data composer.json LICENSE README.md /var/www/html/

# Expose PHP-FPM fastcgi port
EXPOSE 9000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 || exit 1

# Run as www-data user
USER www-data

# Default command
CMD ["php-fpm"]
