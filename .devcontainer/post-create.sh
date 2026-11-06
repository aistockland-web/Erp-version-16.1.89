#!/usr/bin/env bash
set -euo pipefail

cd /workspaces/stockland-erp

echo "=== Stockland ERP Codespaces toolchain ==="
php --version
composer --version
node --version
npm --version
python3 --version
git --version
gh --version

echo "=== Required PHP extensions ==="
php -m
for extension in curl dom fileinfo json mbstring mysqli openssl pdo_mysql simplexml xml zip; do
  if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);"; then
    echo "Missing required PHP extension: $extension" >&2
    exit 1
  fi
done

echo "=== Install locked CSS QA dependencies ==="
if [[ -f ci/cssqa/package-lock.json ]]; then
  npm ci --prefix ci/cssqa --ignore-scripts --no-audit --no-fund
fi

echo "=== Install locked PHPCS and WordPress Coding Standards dependencies ==="
if [[ -f ci/phpcs/composer.lock ]]; then
  composer validate --working-dir=ci/phpcs --strict
  composer install --working-dir=ci/phpcs --no-interaction --prefer-dist --no-progress
fi

echo "=== Install WP-CLI after upstream SHA-256 verification ==="
if ! command -v wp >/dev/null 2>&1; then
  tmp_dir="$(mktemp -d)"
  trap 'rm -rf "$tmp_dir"' EXIT
  curl --fail --silent --show-error --location --retry 3 \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    --output "$tmp_dir/wp-cli.phar"
  curl --fail --silent --show-error --location --retry 3 \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha256 \
    --output "$tmp_dir/wp-cli.phar.sha256"
  expected="$(awk '{print $1}' "$tmp_dir/wp-cli.phar.sha256")"
  actual="$(sha256sum "$tmp_dir/wp-cli.phar" | awk '{print $1}')"
  if [[ -z "$expected" || "$expected" != "$actual" ]]; then
    echo "WP-CLI checksum verification failed; refusing installation." >&2
    exit 1
  fi
  php "$tmp_dir/wp-cli.phar" --info >/dev/null
  sudo install -m 0755 "$tmp_dir/wp-cli.phar" /usr/local/bin/wp
fi
wp --info

echo "=== Private isolated MariaDB test service ==="
mariadb --host="$STOCKLAND_TEST_DB_HOST" \
  --port="$STOCKLAND_TEST_DB_PORT" \
  --user="$STOCKLAND_TEST_DB_USER" \
  "$STOCKLAND_TEST_DB_NAME" \
  --execute='SELECT VERSION() AS mariadb_version, DATABASE() AS test_database;'
echo "Codespace setup checks completed. Use synthetic fixtures only; never load production data here."
