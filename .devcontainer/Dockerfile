FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

USER root
RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
       jq mariadb-client ripgrep shellcheck unzip zip \
    && rm -rf /var/lib/apt/lists/*

USER vscode
