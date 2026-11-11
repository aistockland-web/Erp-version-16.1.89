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
for directory in ci/cssqa ci/phpcs artifacts/codespaces src; do
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

echo "=== Create or reuse the exact repaired 16.2.15 QA candidate ==="
mkdir -p artifacts/codespaces
REPAIRED_PACKAGE="$PWD/artifacts/codespaces/STOCKLAND_ERP_16.2.15_REPAIRED_CANDIDATE_WORDPRESS_INSTALLER.zip"
REPAIRED_SIDECAR="$REPAIRED_PACKAGE.sha256"
REPAIRED_RECEIPT="$PWD/artifacts/codespaces/repair-receipt.json"
REPAIRED_LOCK="$PWD/artifacts/codespaces/repaired-candidate-lock.json"

if [[ -z "${STOCKLAND_DEV_16215_PACKAGE_PATH:-}" && -z "${STOCKLAND_DEV_CANDIDATE_LOCK_PATH:-}" ]]; then
  if [[ -s "$REPAIRED_PACKAGE" && -s "$REPAIRED_SIDECAR" && -s "$REPAIRED_RECEIPT" && -s "$REPAIRED_LOCK" ]]; then
    echo "Reusing the existing repaired candidate and lock; the original .15 archive remains unchanged."
    export STOCKLAND_DEV_16215_PACKAGE_PATH="$REPAIRED_PACKAGE"
    export STOCKLAND_DEV_CANDIDATE_LOCK_PATH="artifacts/codespaces/repaired-candidate-lock.json"
  elif [[ -e "$REPAIRED_PACKAGE" || -e "$REPAIRED_SIDECAR" || -e "$REPAIRED_RECEIPT" || -e "$REPAIRED_LOCK" ]]; then
    echo "A partial repaired-candidate artifact set exists. Inspect artifacts/codespaces before rebuilding; no files were deleted." >&2
    exit 1
  else
    python3 ci/codespaces/repair_candidate_php_entrypoint.py \
      --output-dir "$PWD/artifacts/codespaces" \
      --lock-output "$REPAIRED_LOCK"
    export STOCKLAND_DEV_16215_PACKAGE_PATH="$REPAIRED_PACKAGE"
    export STOCKLAND_DEV_CANDIDATE_LOCK_PATH="artifacts/codespaces/repaired-candidate-lock.json"
  fi
fi

CANDIDATE_LOCK_PATH="${STOCKLAND_DEV_CANDIDATE_LOCK_PATH:-artifacts/codespaces/repaired-candidate-lock.json}"
if [[ ! -f "$CANDIDATE_LOCK_PATH" ]]; then
  echo "Candidate package lock not found: $CANDIDATE_LOCK_PATH" >&2
  exit 1
fi
if [[ ! -s "${STOCKLAND_DEV_16215_PACKAGE_PATH:-}" ]]; then
  echo "The selected repaired .15 candidate archive is missing. Refusing to use the uncorrected original archive for WordPress boot." >&2
  exit 1
fi
python3 ci/codespaces/validate_candidate_packages.py \
  --lock-file "$CANDIDATE_LOCK_PATH" \
  --extract-root /tmp/stockland-candidate-packages

echo "=== Verify the immutable .13 package and materialize editable Stockland source ==="
python3 scripts/codespaces/verify_candidate_archives.py \
  --report artifacts/codespaces/package-intake.json \
  --extract-root /tmp/stockland-candidate-packages
bash scripts/codespaces/bootstrap_project_source.sh

echo "=== Bootstrap isolated WordPress/MariaDB baseline and upgrade test sites ==="
bash scripts/codespaces/bootstrap_wordpress_sites.sh

echo "=== Codespaces environment ready ==="
echo "Private preview ports: 8080 (16.2.13 baseline), 8081 (16.2.13 to 16.2.15 upgrade)."
echo "16.2.18 master installer (MariaDB + MySQL 8): bash .devcontainer/verify-master-installer.sh all"
echo "Optional MySQL 8.0.46 test: bash scripts/codespaces/run_mysql8_parity.sh"
echo "Synthetic QA data only. Never connect this workspace to production."
