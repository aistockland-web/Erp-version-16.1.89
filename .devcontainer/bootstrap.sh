#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="/workspaces/stockland-erp"
CREDENTIAL_DIR="$REPO_ROOT/.local"
CREDENTIAL_FILE="$CREDENTIAL_DIR/wordpress-admin-password"
mkdir -p "$CREDENTIAL_DIR"
chmod 700 "$CREDENTIAL_DIR"

echo "=== Stockland Codespaces toolchain ==="
php --version
composer --version
node --version
npm --version
python3 --version
gh --version

for extension in curl dom fileinfo intl mbstring mysqli openssl pdo_mysql simplexml xml xmlreader xmlwriter zip; do
  if ! php -r "exit(extension_loaded('$extension') ? 0 : 1);"; then
    echo "Missing PHP extension: $extension" >&2
    exit 1
  fi
done

echo "=== Install repository-locked QA dependencies ==="
if [[ -f "$REPO_ROOT/ci/cssqa/package-lock.json" ]]; then
  npm ci --prefix "$REPO_ROOT/ci/cssqa" --ignore-scripts --no-audit --no-fund
fi
if [[ -f "$REPO_ROOT/ci/phpcs/composer.lock" ]]; then
  composer validate --working-dir="$REPO_ROOT/ci/phpcs" --strict
  composer install --working-dir="$REPO_ROOT/ci/phpcs" --no-interaction --prefer-dist --no-progress
fi

echo "=== Install WP-CLI with upstream SHA-256 sidecar verification ==="
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
    echo "WP-CLI checksum mismatch; refusing installation." >&2
    exit 1
  }
  php "$tmp_dir/wp-cli.phar" --info >/dev/null
  install -m 0755 "$tmp_dir/wp-cli.phar" /usr/local/bin/wp
  rm -rf "$tmp_dir"
  trap - EXIT
fi
wp --info

echo "=== Verify isolated database services ==="
MYSQL_PWD=root-dev-only mysql --protocol=tcp --host=db-mariadb --port=3306 --user=root --execute='SELECT VERSION();'
MYSQL_PWD=mysql-dev-only mysql --protocol=tcp --host=db-mysql --port=3306 --user=root --execute='SELECT VERSION();'

echo "=== Wait for WordPress image initialization ==="
for attempt in $(seq 1 60); do
  if [[ -f /var/www/html/wp-config.php && -f /var/www/html/wp-load.php ]]; then
    break
  fi
  sleep 2
done
if [[ ! -f /var/www/html/wp-config.php || ! -f /var/www/html/wp-load.php ]]; then
  echo "WordPress core/config is not available in /var/www/html." >&2
  exit 1
fi

if ! wp core is-installed --path=/var/www/html --allow-root >/dev/null 2>&1; then
  if [[ ! -s "$CREDENTIAL_FILE" ]]; then
    (umask 077; php -r 'echo bin2hex(random_bytes(32));' > "$CREDENTIAL_FILE")
  fi
  ADMIN_PASSWORD="$(cat "$CREDENTIAL_FILE")"
  wp core install --path=/var/www/html --url="http://localhost:8080" \
    --title="Stockland ERP — Isolated WordPress + dbDelta Lab" \
    --admin_user="stockland-lab-admin" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="stockland-lab@example.invalid" --skip-email --allow-root
  unset ADMIN_PASSWORD
fi
wp config set WP_DEBUG true --raw --path=/var/www/html --allow-root
wp config set SAVEQUERIES true --raw --path=/var/www/html --allow-root
wp config set DISALLOW_FILE_EDIT true --raw --path=/var/www/html --allow-root

echo "Codespaces WordPress preview: http://localhost:8080 (private forwarded port)"
echo "Disposable administrator: stockland-lab-admin"
echo "Password stored only in ignored .local/wordpress-admin-password."
echo "Run gh auth login, then bash ci/wp-dbdelta/run-codespaces.sh."
