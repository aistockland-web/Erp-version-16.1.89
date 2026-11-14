#!/usr/bin/env bash
# Usage: verify_master_installer.sh <wp-path> <installer.zip>
# Resets the WordPress DB of a DISPOSABLE site, installs the ZIP through WordPress, activates once, verifies.
set -euo pipefail
WP_PATH="${1:?wordpress path}"; ZIP="${2:?installer zip}"; HERE="$(cd "$(dirname "$0")" && pwd)"
WP="wp --path=$WP_PATH --allow-root"
[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || { echo "Refusing: set STOCKLAND_DISPOSABLE_SITE=yes to confirm this is a throwaway site"; exit 2; }
EXPECTED_DB="${STOCKLAND_DISPOSABLE_DB_NAME:-}"
EXPECTED_HOST="${STOCKLAND_DISPOSABLE_DB_HOST:-}"
ACTUAL_DB="$($WP config get DB_NAME 2>/dev/null | tail -1)"
ACTUAL_HOST="$($WP config get DB_HOST 2>/dev/null | tail -1)"
[[ -n "$EXPECTED_DB" && "$ACTUAL_DB" == "$EXPECTED_DB" ]] || { echo "Refusing: WordPress database name does not match STOCKLAND_DISPOSABLE_DB_NAME"; exit 2; }
COMPOSE_MODE=no
if printenv STOCKLAND_CODESPACES_COMPOSE >/dev/null 2>&1; then
  COMPOSE_MODE="$(printenv STOCKLAND_CODESPACES_COMPOSE)"
fi
case "$EXPECTED_HOST" in
  127.0.0.1)
    [[ "$ACTUAL_HOST" == "127.0.0.1" ]] || { echo "Refusing: WordPress database host differs from the expected loopback host." >&2; exit 2; }
    ;;
  mariadb:3306|mysql8:3306)
    [[ "$COMPOSE_MODE" == "yes" && "$ACTUAL_HOST" == "$EXPECTED_HOST" ]] || {
      echo "Refusing: Compose database hosts are permitted only in the explicit isolated Codespaces Compose mode." >&2
      exit 2
    }
    ;;
  *)
    echo "Refusing: database host must be loopback or an allowlisted internal Codespaces Compose service." >&2
    exit 2
    ;;
esac
$WP db reset --yes
$WP core install --url=http://localhost --title=Stockland-QA --admin_user=admin --admin_password="$(openssl rand -hex 12)" --admin_email=qa@example.com --skip-email
rm -rf "$WP_PATH/wp-content/plugins/stockland-erp-app"
$WP --user=admin plugin install "$ZIP" --activate
$WP --user=admin eval-file "$HERE/complete_baseline.php"
$WP --user=admin eval-file "$HERE/verify_master_installer.php"
