#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

SITE="/var/www/html/site-mysql8"
PACKAGE="$REPO_ROOT/STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip"
[[ -f "$PACKAGE" ]] || { echo "Missing 16.2.15 candidate archive." >&2; exit 1; }

wp_as_www() {
  sudo -u www-data env HOME=/tmp wp "$@"
}

for attempt in $(seq 1 120); do
  if [[ -f "$SITE/wp-config.php" && -f "$SITE/wp-load.php" ]]; then
    break
  fi
  sleep 2
done
[[ -f "$SITE/wp-config.php" ]] || { echo "MySQL 8 WordPress did not initialize." >&2; exit 1; }

if ! wp_as_www --path="$SITE" core is-installed --skip-plugins --skip-themes >/dev/null 2>&1; then
  wp_as_www --path="$SITE" core install --url="http://localhost:8082" --title="Stockland ERP 16.2.15 MySQL 8.0.46 QA" --admin_user="$STOCKLAND_DEV_WP_ADMIN_USER" --admin_password="$STOCKLAND_DEV_WP_ADMIN_PASSWORD" --admin_email="qa@example.invalid" --skip-email
fi

wp_as_www --path="$SITE" plugin install "$PACKAGE" --force --activate
wp_as_www --path="$SITE" plugin is-active stockland-erp-app
ACTUAL="$(wp_as_www --path="$SITE" plugin get stockland-erp-app --field=version)"
[[ "$ACTUAL" == "16.2.15" ]] || { echo "MySQL 8 expected 16.2.15, found $ACTUAL" >&2; exit 1; }

echo "PASS clean WordPress install and 16.2.15 activation on MySQL 8.0.46."
