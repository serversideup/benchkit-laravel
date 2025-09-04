# syntax=docker/dockerfile:1
# check=skip=SecretsUsedInArgOrEnv
############################################
# PHP - Base Image
############################################
ARG PHP_VARIATION=fpm-nginx
ARG PHP_VERSION=8.4
ARG BASE_OS=trixie
ARG BASE_IMAGE=serversideup/php-dev:283-${PHP_VERSION}-${PHP_VARIATION}-${BASE_OS}

# Learn more about the Server Side Up PHP Docker Images at:
# https://serversideup.net/open-source/docker-php/
FROM ${BASE_IMAGE} AS php-base

# Install Yet Another Bench Script
ADD --keep-git-dir=false --chmod=755 --chown=www-data:www-data https://github.com/masonr/yet-another-bench-script.git /opt/yet-another-bench-script/

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
    VITE_APP_NAME="BenchKit" \
    MAIL_ENCRYPTION=

############################################
# PHP - Development Image
############################################
FROM php-base AS php-development
ARG PHP_VARIATION=fpm-nginx

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
    docker-php-serversideup-set-file-permissions --owner $USER_ID:$GROUP_ID --service ${PHP_VARIATION#fpm-} --dir /opt/

# Drop privileges back to www-data    
USER www-data

############################################
# PHP - CI Image
############################################
FROM php-base AS php-ci

# Sometimes CI images need to run as root
# so we set the ROOT user and configure
# the PHP-FPM pool to run as www-data
USER root
RUN if command -v php-fpm > /dev/null 2>&1; then \
      echo "" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
      echo "user = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf && \
      echo "group = www-data" >> /usr/local/etc/php-fpm.d/docker-php-serversideup-pool.conf; \
    fi

############################################
# Node.js - Base Image
############################################
FROM node:22 AS node-base

############################################
# Node.js - Development Image
############################################
FROM node-base AS node-development

# We can pass USER_ID and GROUP_ID as build arguments
# to ensure the node user has the same UID and GID
# as the user running Docker.
ARG USER_ID
ARG GROUP_ID

# Switch to root so we can set the user ID and group ID
USER root

# Script to handle existing group ID
RUN if getent group "$GROUP_ID" > /dev/null; then \
        moved_group_id="99$GROUP_ID"; \
        existing_group_name=$(getent group "$GROUP_ID" | cut -d: -f1); \
        echo "Moving GID of $existing_group_name to $moved_group_id..."; \
        groupmod -g "$moved_group_id" "$existing_group_name"; \
    fi

# Script to handle existing user ID
RUN if getent passwd "$USER_ID" > /dev/null; then \
        moved_user_id="99$USER_ID"; \
        existing_username=$(getent passwd "$USER_ID" | cut -d: -f1); \
        echo "Moving UID of $existing_username to $moved_user_id..."; \
        usermod -u "$moved_user_id" "$existing_username"; \
    fi

# Set the user ID and group ID for the node user
RUN groupmod -g $GROUP_ID node && usermod -u $USER_ID -g $GROUP_ID node

# Drop privileges back to node user
USER node

############################################
# Node.js - CI Image
############################################
FROM node-base AS node-ci

############################################
# Node.js - Build Image
############################################
FROM node-base AS node-build

WORKDIR /usr/src/app/

COPY --chown=node:node . /usr/src/app/

RUN yarn install --frozen-lockfile; \
    yarn run build

############################################
# Final Image
############################################
FROM php-base AS final
ARG REPOSITORY_BUILD_VERSION="dev"
COPY --chown=www-data:www-data . /var/www/html
COPY --chown=www-data:www-data --from=node-build /usr/src/app/public/build /var/www/html/public/build
COPY --chmod=755 ./.infrastructure/entrypoint.d/ /etc/entrypoint.d/

# Default to running the Laravel Automations with serversideup/php
ENV AUTORUN_ENABLED="true"

USER www-data

WORKDIR /var/www/html

# Create the SQLite directory and set the owner to www-data (remove this if you're not using SQLite)
RUN mkdir -p /var/www/html/.infrastructure/volume_data/sqlite/; \
    chown -R www-data:www-data /var/www/html/.infrastructure/volume_data/sqlite/; \
    \
    # Set the build version
    echo "${REPOSITORY_BUILD_VERSION}" > /var/www/html/.build-version; \
    \
    # List the composer packages
    composer show --locked; \
    \
    # Install Composer dependencies
    composer install --optimize-autoloader --no-interaction --no-progress --no-ansi