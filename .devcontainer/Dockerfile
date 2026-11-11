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

RUN set -eu; \
    tmp_dir="$(mktemp -d)"; \
    trap 'rm -rf "$tmp_dir"' EXIT; \
    curl --fail --silent --show-error --location --retry 3 \
      https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
      --output "$tmp_dir/wp-cli.phar"; \
    curl --fail --silent --show-error --location --retry 3 \
      https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha256 \
      --output "$tmp_dir/wp-cli.phar.sha256"; \
    expected="$(awk '{print $1}' "$tmp_dir/wp-cli.phar.sha256")"; \
    actual="$(sha256sum "$tmp_dir/wp-cli.phar" | awk '{print $1}')"; \
    test -n "$expected" && test "$expected" = "$actual"; \
    php "$tmp_dir/wp-cli.phar" --info >/dev/null; \
    install -m 0755 "$tmp_dir/wp-cli.phar" /usr/local/bin/wp

USER vscode
