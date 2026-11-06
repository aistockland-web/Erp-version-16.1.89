FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

USER root
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       ca-certificates curl jq mariadb-client ripgrep shellcheck unzip zip \
       libcurl4-openssl-dev libonig-dev libxml2-dev libzip-dev \
    && for extension in curl dom mbstring mysqli pdo pdo_mysql simplexml xml xmlreader xmlwriter zip; do \
         if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);"; then \
           docker-php-ext-install -j"$(nproc)" "$extension"; \
         fi; \
       done \
    && rm -rf /var/lib/apt/lists/*

USER vscode
