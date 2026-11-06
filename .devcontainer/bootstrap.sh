#!/usr/bin/env bash
set -euo pipefail
cd /var/www/html
for attempt in $(seq 1 60); do
  if [[ -f wp-config.php && -f wp-load.php ]]; then break; fi
  sleep 2
done
if [[ ! -f wp-config.php || ! -f wp-load.php ]]; then
  echo "WordPress core/config did not become available in the container." >&2
  exit 1
fi
if ! wp core is-installed --allow-root >/dev/null 2>&1; then
  wp core install --url="http://localhost:8080" --title="Stockland ERP — Isolated Codespaces Lab" \
    --admin_user="stockland-lab-admin" --admin_password="Stockland-Lab-Only-2026!" \
    --admin_email="stockland-lab@example.invalid" --skip-email --allow-root
fi
wp config set WP_DEBUG true --raw --allow-root
wp config set SAVEQUERIES true --raw --allow-root
wp config set DISALLOW_FILE_EDIT true --raw --allow-root
echo "WordPress Codespaces lab is ready at http://localhost:8080"
echo "Disposable administrator: stockland-lab-admin (password: Stockland-Lab-Only-2026!)"
echo "Run: bash ci/wp-dbdelta/run-codespaces.sh"
