#!/usr/bin/env bash
set -euo pipefail
cd /workspaces/stockland-erp

php --version
composer --version
python3 --version

if command -v node >/dev/null 2>&1 && [[ -f ci/cssqa/package-lock.json ]]; then
  node --version
  npm --version
  npm ci --prefix ci/cssqa --ignore-scripts --no-audit --no-fund
fi

if [[ -f ci/phpcs/composer.lock ]]; then
  composer validate --working-dir=ci/phpcs --strict
  composer install --working-dir=ci/phpcs --no-interaction --prefer-dist --no-progress
fi

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
  [[ -n "$expected" && "$expected" == "$actual" ]] || {
    echo "WP-CLI checksum verification failed." >&2
    exit 1
  }
  php "$tmp_dir/wp-cli.phar" --info >/dev/null
  sudo install -m 0755 "$tmp_dir/wp-cli.phar" /usr/local/bin/wp
fi
wp --info

mariadb --host="$STOCKLAND_TEST_DB_HOST" --port="$STOCKLAND_TEST_DB_PORT" \
  --user="$STOCKLAND_TEST_DB_USER" --password="$STOCKLAND_TEST_DB_PASSWORD" \
  "$STOCKLAND_TEST_DB_NAME" --execute='SELECT VERSION(), DATABASE();'
mysql --host="$STOCKLAND_MYSQL_DB_HOST" --port="$STOCKLAND_MYSQL_DB_PORT" \
  --user="$STOCKLAND_MYSQL_DB_USER" --password="$STOCKLAND_MYSQL_DB_PASSWORD" \
  "$STOCKLAND_MYSQL_DB_NAME" --execute='SELECT VERSION(), DATABASE();'

python3 ci/codespaces/verify_installers.py --verify-all
bash ci/codespaces/acceptance.sh
echo "Stockland Codespaces smoke complete. Business workflow and production gates remain separate."
