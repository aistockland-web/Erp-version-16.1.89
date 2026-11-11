#!/usr/bin/env bash
set -euo pipefail
ROOT="/workspaces/stockland-erp"
cd "$ROOT"

echo "=== Stockland ERP toolchain ==="
php --version
composer --version
node --version
npm --version
python3 --version
git --version
gh --version

for extension in curl dom fileinfo json mbstring mysqli openssl pdo_mysql simplexml xml zip; do
  php -r "exit(extension_loaded('$extension') ? 0 : 1);" || {
    echo "Missing PHP extension: $extension" >&2
    exit 1
  }
done

if [[ -f ci/cssqa/package-lock.json ]]; then
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
    echo "WP-CLI SHA-256 verification failed." >&2
    exit 1
  }
  php "$tmp_dir/wp-cli.phar" --info >/dev/null
  sudo install -m 0755 "$tmp_dir/wp-cli.phar" /usr/local/bin/wp
fi
wp --info

python3 scripts/codespaces/verify_candidate_archives.py \
  --report "$STOCKLAND_ARTIFACT_DIR/package-intake.json" \
  --extract-root /tmp/stockland-candidate-packages
bash scripts/codespaces/bootstrap_wordpress.sh
echo "Codespaces ready. Both WordPress sites contain synthetic QA data only."
