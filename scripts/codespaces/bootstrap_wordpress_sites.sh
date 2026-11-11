#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

PACKAGE_16213="$REPO_ROOT/STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip"
PACKAGE_16215="$REPO_ROOT/STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip"
BASELINE_SITE="/var/www/html/site-baseline"
UPGRADE_SITE="/var/www/html/site-upgrade"

for package in "$PACKAGE_16213" "$PACKAGE_16215"; do
  [[ -f "$package" ]] || { echo "Missing candidate package: $package" >&2; exit 1; }
done

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

wait_for_site() {
  local site="$1"
  local attempt
  for attempt in $(seq 1 120); do
    if [[ -f "$site/wp-config.php" && -f "$site/wp-load.php" ]]; then
      return 0
    fi
    sleep 2
  done
  echo "WordPress files were not initialized at $site" >&2
  exit 1
}

install_core_if_needed() {
  local site="$1"
  local url="$2"
  local title="$3"
  if ! wp_as_www "$site" core is-installed --skip-plugins --skip-themes >/dev/null 2>&1; then
    wp_as_www "$site" core install --url="$url" --title="$title" --admin_user="$STOCKLAND_DEV_WP_ADMIN_USER" --admin_password="$STOCKLAND_DEV_WP_ADMIN_PASSWORD" --admin_email="qa@example.invalid" --skip-email
  fi
}

install_local_plugin() {
  local site="$1"
  local archive="$2"
  wp_as_www "$site" plugin install "$archive" --force --activate
}

assert_plugin_version() {
  local site="$1"
  local expected="$2"
  local actual
  wp_as_www "$site" plugin is-active stockland-erp-app
  actual="$(wp_as_www "$site" plugin get stockland-erp-app --field=version --skip-plugins --skip-themes)"
  if [[ "$actual" != "$expected" ]]; then
    echo "Expected plugin $expected at $site, found $actual" >&2
    exit 1
  fi
  echo "PASS plugin $expected active at $site"
}

wait_for_site "$BASELINE_SITE"
wait_for_site "$UPGRADE_SITE"

echo "=== MariaDB 10.11 — clean install of operational baseline 16.2.13 ==="
install_core_if_needed "$BASELINE_SITE" "http://localhost:8080" "Stockland ERP 16.2.13 Baseline QA"
install_local_plugin "$BASELINE_SITE" "$PACKAGE_16213"
assert_plugin_version "$BASELINE_SITE" "16.2.13"

echo "=== MariaDB 10.11 — upgrade path from 16.2.13 to 16.2.15 ==="
install_core_if_needed "$UPGRADE_SITE" "http://localhost:8081" "Stockland ERP 16.2.15 Upgrade QA"
install_local_plugin "$UPGRADE_SITE" "$PACKAGE_16213"
assert_plugin_version "$UPGRADE_SITE" "16.2.13"
install_local_plugin "$UPGRADE_SITE" "$PACKAGE_16215"
assert_plugin_version "$UPGRADE_SITE" "16.2.15"

echo "PASS both isolated MariaDB WordPress sites are initialized."
