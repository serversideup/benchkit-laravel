############################################
# Base Image
############################################
ARG BASE_IMAGE_TAG=8.4-fpm-nginx-alpine

# Learn more about the Server Side Up PHP Docker Images at:
# https://serversideup.net/open-source/docker-php/
FROM serversideup/php:${BASE_IMAGE_TAG} AS base

## Uncomment if you need to install additional PHP extensions
# USER root
# RUN install-php-extensions bcmath gd

## Laravel Environment Variable Defaults
ENV APP_NAME=BenchKit \
    APP_ENV=production \
    APP_DEBUG=false \
    APP_URL=https://example.com \
    \
    APP_LOCALE=en \
    APP_FALLBACK_LOCALE=en \
    APP_FAKER_LOCALE=en_US \
    \
    APP_MAINTENANCE_DRIVER=file \
    # APP_MAINTENANCE_STORE=database \
    \
    PHP_CLI_SERVER_WORKERS=4 \
    \
    BCRYPT_ROUNDS=12 \
    \
    LOG_CHANNEL=stack \
    LOG_STACK=single \
    LOG_DEPRECATIONS_CHANNEL=null \
    LOG_LEVEL=debug \
    \
    DB_CONNECTION=sqlite \
    DB_DATABASE=/var/www/html/.infrastructure/volume_data/sqlite/database.sqlite \
    # DB_HOST=127.0.0.1 \
    # DB_PORT=3306 \
    # DB_USERNAME=root \
    # DB_PASSWORD= \
    \
    SESSION_DRIVER=database \
    SESSION_LIFETIME=120 \
    SESSION_ENCRYPT=false \
    SESSION_PATH=/ \
    SESSION_DOMAIN=null \
    \
    BROADCAST_CONNECTION=log \
    FILESYSTEM_DISK=local \
    QUEUE_CONNECTION=database \
    \
    CACHE_STORE=database \
    # CACHE_PREFIX= \
    \
    MEMCACHED_HOST=127.0.0.1 \
    \
    REDIS_CLIENT=phpredis \
    REDIS_HOST=127.0.0.1 \
    REDIS_PASSWORD=null \
    REDIS_PORT=6379 \
    \
    MAIL_MAILER=smtp \
    MAIL_SCHEME=null \
    MAIL_HOST=mailpit \
    MAIL_PORT=1025 \
    MAIL_USERNAME= \
    MAIL_PASSWORD= \
    MAIL_FROM_ADDRESS="hello@example.com" \
    MAIL_FROM_NAME= \
    \
    AWS_ACCESS_KEY_ID= \
    AWS_SECRET_ACCESS_KEY= \
    AWS_DEFAULT_REGION=us-east-1 \
    AWS_BUCKET= \
    AWS_USE_PATH_STYLE_ENDPOINT=false \
    \
    VITE_APP_NAME="${APP_NAME}" \
    MAIL_ENCRYPTION=

############################################
# Development Image
############################################
FROM base AS development

# We can pass USER_ID and GROUP_ID as build arguments
# to ensure the www-data user has the same UID and GID
# as the user running Docker.
ARG USER_ID
ARG GROUP_ID

# Switch to root so we can set the user ID and group ID
USER root

# Trust the self-signed certificate
COPY .infrastructure/conf/traefik/dev/certificates/local-ca.pem /usr/local/share/ca-certificates/local-ca.crt
RUN update-ca-certificates

# Set the user ID and group ID for www-data
RUN docker-php-serversideup-set-id www-data $USER_ID:$GROUP_ID  && \
    docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID --service nginx

# Drop privileges back to www-data    
USER www-data

############################################
# CI image
############################################
FROM base AS ci

# Sometimes CI images need to run as root
# so we set the ROOT user and configure
# the PHP-FPM pool to run as www-data
USER root
RUN echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \ 
    echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
    echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf

############################################
# Production Image
############################################
FROM base AS final
COPY --chown=www-data:www-data . /var/www/html

# Create the SQLite directory and set the owner to www-data (remove this if you're not using SQLite)
RUN mkdir -p /var/www/html/.infrastructure/volume_data/sqlite/ && \
    chown -R www-data:www-data /var/www/html/.infrastructure/volume_data/sqlite/

VOLUME /var/www/html/.env

USER www-data