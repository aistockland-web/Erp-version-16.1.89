#!/usr/bin/env bash
# Usage: verify_master_installer.sh <wp-path> <installer.zip>
# Resets the WordPress DB of a DISPOSABLE site, installs the ZIP through WordPress, activates once, verifies.
set -Eeuo pipefail
STAGE="startup"
trap 'status=$?; echo "FAIL verify_master_installer.sh stage=$STAGE line=$LINENO exit=$status" >&2; exit "$status"' ERR
WP_PATH="${1:?wordpress path}"; ZIP="${2:?installer zip}"; HERE="$(cd "$(dirname "$0")" && pwd)"
WP="php -d display_errors=1 -d error_reporting=-1 /usr/local/bin/wp --path=$WP_PATH --allow-root"
[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || { echo "Refusing: set STOCKLAND_DISPOSABLE_SITE=yes to confirm this is a throwaway site"; exit 2; }
EXPECTED_DB="${STOCKLAND_DISPOSABLE_DB_NAME:-}"
EXPECTED_HOST="${STOCKLAND_DISPOSABLE_DB_HOST:-}"
ACTUAL_DB="$($WP config get DB_NAME 2>/dev/null | tail -1)"
ACTUAL_HOST="$($WP config get DB_HOST 2>/dev/null | tail -1)"
[[ -n "$EXPECTED_DB" && "$ACTUAL_DB" == "$EXPECTED_DB" ]] || { echo "Refusing: WordPress database name does not match STOCKLAND_DISPOSABLE_DB_NAME"; exit 2; }
[[ "$EXPECTED_HOST" == "127.0.0.1" && "$ACTUAL_HOST" == "127.0.0.1" ]] || { echo "Refusing: WordPress database host must be explicitly loopback (127.0.0.1)"; exit 2; }
STAGE="database reset"; echo "== $STAGE"; $WP db reset --yes
STAGE="WordPress core install"; echo "== $STAGE"; $WP core install --url=http://localhost --title=Stockland-QA --admin_user=admin --admin_password="$(openssl rand -hex 12)" --admin_email=qa@example.com --skip-email
STAGE="remove any prior disposable plugin directory"; echo "== $STAGE"; rm -rf "$WP_PATH/wp-content/plugins/stockland-erp-app"
STAGE="install and activate the ZIP through WordPress"; echo "== $STAGE"; $WP --user=admin plugin install "$ZIP" --activate
STAGE="complete the bounded 49-key baseline wizard"; echo "== $STAGE"; $WP --user=admin eval-file "$HERE/complete_baseline.php"
STAGE="verify the installed schema and HR fail-closed gate"; echo "== $STAGE"; $WP --user=admin eval-file "$HERE/verify_master_installer.php"
STAGE="complete"; echo "PASS verifier stages completed"
