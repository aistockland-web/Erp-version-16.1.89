#!/usr/bin/env bash
# Run WP-CLI in the workspace container while supplying the DB environment
# expected by the official WordPress Docker image's generated wp-config.php.
# The WordPress service containers are separate from this workspace process.
wp_as_www() {
  local site_path=""
  local db_host=""
  local db_name=""

  for argument in "$@"; do
    case "$argument" in
      --path=*)
        site_path="${argument#--path=}"
        break
        ;;
    esac
  done

  case "$site_path" in
    /var/www/html/site-baseline)
      db_host="mariadb:3306"
      db_name="${STOCKLAND_DEV_DB_BASELINE:-stockland_wp_baseline}"
      ;;
    /var/www/html/site-upgrade)
      db_host="mariadb:3306"
      db_name="${STOCKLAND_DEV_DB_UPGRADE:-stockland_wp_upgrade}"
      ;;
    /var/www/html/site-mysql8)
      db_host="mysql8:3306"
      db_name="stockland_wp_mysql8"
      ;;
    *)
      echo "Unknown Stockland QA WordPress path: $site_path" >&2
      return 2
      ;;
  esac

  : "${STOCKLAND_DEV_DB_USER:?Missing STOCKLAND_DEV_DB_USER in workspace environment}"
  : "${STOCKLAND_DEV_DB_PASSWORD:?Missing STOCKLAND_DEV_DB_PASSWORD in workspace environment}"

  sudo -u www-data env \
    HOME=/tmp \
    WORDPRESS_DB_HOST="$db_host" \
    WORDPRESS_DB_NAME="$db_name" \
    WORDPRESS_DB_USER="$STOCKLAND_DEV_DB_USER" \
    WORDPRESS_DB_PASSWORD="$STOCKLAND_DEV_DB_PASSWORD" \
    wp "$@"
}
