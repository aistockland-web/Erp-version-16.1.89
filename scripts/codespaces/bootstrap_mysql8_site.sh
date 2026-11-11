#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

SITE="/var/www/html/site-mysql8"
PACKAGE="$REPO_ROOT/STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip"
[[ -f "$PACKAGE" ]] || { echo "Missing 16.2.15 candidate archive." >&2; exit 1; }

wp_as_www() {
  local site="${1:?WordPress site path is required}"
  shift
  local db_host
  local db_name

  case "$site" in
    /var/www/html/site-baseline)
      db_host="${STOCKLAND_DEV_DB_HOST:?Missing MariaDB host}"
      db_name="${STOCKLAND_DEV_DB_BASELINE:?Missing baseline database name}"
      ;;
    /var/www/html/site-upgrade)
      db_host="${STOCKLAND_DEV_DB_HOST:?Missing MariaDB host}"
      db_name="${STOCKLAND_DEV_DB_UPGRADE:?Missing upgrade database name}"
      ;;
    /var/www/html/site-mysql8)
      db_host="${STOCKLAND_DEV_MYSQL8_DB_HOST:-mysql8}"
      db_name="${STOCKLAND_DEV_MYSQL8_DB_NAME:-stockland_wp_mysql8}"
      ;;
    *)
      echo "Unknown isolated WordPress site: $site" >&2
      return 2
      ;;
  esac

  sudo -u www-data env HOME=/tmp \
    WORDPRESS_DB_HOST="$db_host:${STOCKLAND_DEV_DB_PORT:-3306}" \
    WORDPRESS_DB_NAME="$db_name" \
    WORDPRESS_DB_USER="${STOCKLAND_DEV_DB_USER:?Missing WordPress database user}" \
    WORDPRESS_DB_PASSWORD="${STOCKLAND_DEV_DB_PASSWORD:?Missing WordPress database password}" \
    wp --path="$site" "$@"
}

for attempt in $(seq 1 120); do
  if [[ -f "$SITE/wp-config.php" && -f "$SITE/wp-load.php" ]]; then
    break
  fi
  sleep 2
done
[[ -f "$SITE/wp-config.php" ]] || { echo "MySQL 8 WordPress did not initialize." >&2; exit 1; }

if ! wp_as_www "$SITE" core is-installed --skip-plugins --skip-themes >/dev/null 2>&1; then
  wp_as_www "$SITE" core install --url="http://localhost:8082" --title="Stockland ERP 16.2.15 MySQL 8.0.46 QA" --admin_user="$STOCKLAND_DEV_WP_ADMIN_USER" --admin_password="$STOCKLAND_DEV_WP_ADMIN_PASSWORD" --admin_email="qa@example.invalid" --skip-email
fi

wp_as_www "$SITE" plugin install "$PACKAGE" --force --activate
wp_as_www "$SITE" plugin is-active stockland-erp-app
ACTUAL="$(wp_as_www "$SITE" plugin get stockland-erp-app --field=version)"
[[ "$ACTUAL" == "16.2.15" ]] || { echo "MySQL 8 expected 16.2.15, found $ACTUAL" >&2; exit 1; }

echo "PASS clean WordPress install and 16.2.15 activation on MySQL 8.0.46."
