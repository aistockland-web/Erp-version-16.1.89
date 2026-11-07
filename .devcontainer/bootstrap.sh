#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html
CREDENTIAL_DIR="/workspace/.local"
CREDENTIAL_FILE="$CREDENTIAL_DIR/wordpress-admin-password"

for attempt in $(seq 1 60); do
  if [[ -f wp-config.php && -f wp-load.php ]]; then break; fi
  sleep 2
done
if [[ ! -f wp-config.php || ! -f wp-load.php ]]; then
  echo "WordPress core/config did not become available in the container." >&2
  exit 1
fi

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
  mkdir -p "$CREDENTIAL_DIR"
  chmod 700 "$CREDENTIAL_DIR"

  if [[ ! -s "$CREDENTIAL_FILE" ]]; then
    ADMIN_PASSWORD="$(php -r 'echo bin2hex(random_bytes(32));')"
    (umask 077; printf '%s\n' "$ADMIN_PASSWORD" > "$CREDENTIAL_FILE")
  else
    ADMIN_PASSWORD="$(cat "$CREDENTIAL_FILE")"
  fi

  wp core install --url="http://localhost:8080" --title="Stockland ERP — Isolated Codespaces Lab" \
    --admin_user="stockland-lab-admin" --admin_password="$ADMIN_PASSWORD" \
    --admin_email="stockland-lab@example.invalid" --skip-email --allow-root
  unset ADMIN_PASSWORD
fi

wp config set WP_DEBUG true --raw --allow-root
wp config set SAVEQUERIES true --raw --allow-root
wp config set DISALLOW_FILE_EDIT true --raw --allow-root

echo "WordPress Codespaces lab is ready at http://localhost:8080"
echo "Disposable administrator: stockland-lab-admin"
if [[ -s "$CREDENTIAL_FILE" ]]; then
  echo "Random password stored in the ignored workspace file: $CREDENTIAL_FILE"
  echo "Read it in the private Codespaces terminal with: cat .local/wordpress-admin-password"
else
  echo "WordPress was already installed; this bootstrap did not change its administrator password."
fi
echo "Run: bash ci/wp-dbdelta/run-codespaces.sh"
