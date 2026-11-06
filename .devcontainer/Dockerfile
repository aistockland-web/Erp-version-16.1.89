FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

USER root
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       jq mariadb-client ripgrep shellcheck unzip zip \
       libcurl4-openssl-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
       curl dom mbstring mysqli pdo_mysql simplexml xml xmlreader xmlwriter zip \
    && rm -rf /var/lib/apt/lists/*

USER vscode
