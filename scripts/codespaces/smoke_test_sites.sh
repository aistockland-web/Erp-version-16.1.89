#!/usr/bin/env bash
set -euo pipefail

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

smoke_site() {
  local site="$1"
  local service="$2"
  local host_header="$3"
  local expected="$4"
  local version

  wp_as_www "$site" core is-installed --skip-plugins --skip-themes
  wp_as_www "$site" plugin is-active stockland-erp-app
  version="$(wp_as_www "$site" plugin get stockland-erp-app --field=version)"
  [[ "$version" == "$expected" ]] || { echo "Expected $expected at $site; found $version" >&2; return 1; }

  curl --fail --silent --show-error --retry 10 --retry-all-errors -H "Host: $host_header" "http://$service/wp-login.php" >/dev/null
  curl --fail --silent --show-error --retry 10 --retry-all-errors -H "Host: $host_header" "http://$service/wp-json/" | python3 -c 'import json,sys; data=json.load(sys.stdin); assert isinstance(data.get("namespaces"), list)'

  echo "PASS WordPress login, REST index and plugin $version at $site"
}

smoke_site "/var/www/html/site-baseline" "wordpress_baseline" "localhost:8080" "16.2.13"
smoke_site "/var/www/html/site-upgrade" "wordpress_upgrade" "localhost:8081" "16.2.15"

if [[ -f /var/www/html/site-mysql8/wp-config.php ]]; then
  smoke_site "/var/www/html/site-mysql8" "wordpress_mysql8" "localhost:8082" "16.2.15"
fi

echo "PASS Stockland isolated WordPress smoke checks."
