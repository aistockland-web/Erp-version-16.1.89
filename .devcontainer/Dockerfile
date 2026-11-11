FROM node:22.16.0-bookworm-slim AS node-runtime
FROM mcr.microsoft.com/devcontainers/php:8.3-bookworm

COPY --from=node-runtime /usr/local/ /usr/local/

USER root
RUN apt-get update && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends ca-certificates curl jq mariadb-client ripgrep shellcheck unzip zip python3 python3-pip python3-venv sudo libcurl4-openssl-dev libonig-dev libxml2-dev libzip-dev && for extension in curl dom mbstring mysqli pdo pdo_mysql simplexml xml xmlreader xmlwriter zip; do if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);"; then docker-php-ext-install -j"$(nproc)" "$extension"; fi; done && rm -rf /var/lib/apt/lists/*

# Passwordless sudo is limited to this disposable development container. It lets
# the non-root workspace user prepare QA-only bind-mount folders and run WP-CLI
# as the WordPress www-data account; it is not a production image or credential.
RUN printf '%s\\n' 'vscode ALL=(ALL) NOPASSWD:ALL' > /etc/sudoers.d/vscode && chmod 0440 /etc/sudoers.d/vscode && visudo -cf /etc/sudoers.d/vscode

USER vscode
WORKDIR /workspaces/stockland-erp
