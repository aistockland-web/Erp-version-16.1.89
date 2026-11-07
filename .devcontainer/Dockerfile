FROM wordpress:php8.3-apache

USER root
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       ca-certificates curl git jq mariadb-client ripgrep shellcheck unzip zip composer \
       libcurl4-openssl-dev libicu-dev libonig-dev libxml2-dev libzip-dev \
    && for extension in curl dom fileinfo intl mbstring mysqli pdo_mysql simplexml xmlreader xmlwriter zip; do \
         if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);"; then \
           docker-php-ext-install -j"$(nproc)" "$extension"; \
         fi; \
       done \
    && rm -rf /var/lib/apt/lists/*

ENV WP_CLI_ALLOW_ROOT=1
WORKDIR /workspaces/stockland-erp
