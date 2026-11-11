#!/usr/bin/env bash
set -euo pipefail

cd /workspaces/stockland-erp

# Keep the mounted Codespaces checkout trusted when CI uses a different host UID.
git config --global --add safe.directory "$PWD"

echo "=== Stockland ERP Codespaces toolchain ==="
php --version
composer --version
node --version
npm --version
python3 --version
git --version
if command -v gh >/dev/null 2>&1; then gh --version; fi

echo "=== Prepare only generated QA directories for the non-root workspace user ==="
WORKSPACE_UID="$(id -u)"
WORKSPACE_GID="$(id -g)"
for directory in ci/cssqa ci/phpcs artifacts/codespaces; do
  sudo install -d -m 0755 -o "$WORKSPACE_UID" -g "$WORKSPACE_GID" "$directory"
done
for generated in ci/cssqa/node_modules ci/phpcs/vendor; do
  if [[ -e "$generated" ]]; then
    sudo chown -R "$WORKSPACE_UID:$WORKSPACE_GID" "$generated"
  fi
done

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

echo "=== Install WP-CLI after SHA-256 verification ==="
if ! command -v wp >/dev/null 2>&1; then
  tmp_dir="$(mktemp -d)"
  trap 'rm -rf "$tmp_dir"' EXIT
  curl --fail --silent --show-error --location --retry 3 https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar --output "$tmp_dir/wp-cli.phar"
  curl --fail --silent --show-error --location --retry 3 https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha256 --output "$tmp_dir/wp-cli.phar.sha256"
  expected="$(awk '{print $1}' "$tmp_dir/wp-cli.phar.sha256")"
  actual="$(sha256sum "$tmp_dir/wp-cli.phar" | awk '{print $1}')"
  if [[ -z "$expected" || "$expected" != "$actual" ]]; then
    echo "WP-CLI checksum verification failed; refusing installation." >&2
    exit 1
  fi
  php "$tmp_dir/wp-cli.phar" --info >/dev/null
  sudo install -m 0755 "$tmp_dir/wp-cli.phar" /usr/local/bin/wp
  rm -rf "$tmp_dir"
  trap - EXIT
fi
wp --info

echo "=== Verify the exact locked baseline and selected repaired candidate ==="
CANDIDATE_LOCK_PATH="${STOCKLAND_DEV_CANDIDATE_LOCK_PATH:-ci/codespaces/candidate-packages.json}"
if [[ ! -f "$CANDIDATE_LOCK_PATH" ]]; then
  echo "Candidate package lock not found: $CANDIDATE_LOCK_PATH" >&2
  exit 1
fi
python3 ci/codespaces/validate_candidate_packages.py --lock-file "$CANDIDATE_LOCK_PATH" --extract-root /tmp/stockland-candidate-packages

echo "=== Bootstrap isolated WordPress/MariaDB baseline and upgrade test sites ==="
bash scripts/codespaces/bootstrap_wordpress_sites.sh

echo "=== Codespaces environment ready ==="
echo "Private preview ports: 8080 (16.2.13 baseline), 8081 (16.2.13 to 16.2.15 upgrade)."
echo "Optional MySQL 8.0.46 test: bash scripts/codespaces/run_mysql8_parity.sh"
echo "Synthetic QA data only. Never connect this workspace to production."
