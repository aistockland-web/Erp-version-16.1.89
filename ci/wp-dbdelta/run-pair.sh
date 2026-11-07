#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 3 || $# -gt 4 ]]; then
  echo "Usage: $0 <label> <baseline-16.1.95.zip> <candidate-16.2.9.zip> [expected-approval: DRAFT|APPROVED]" >&2
  exit 2
fi

LABEL="$1"
BASELINE_ZIP="$(realpath "$2")"
CANDIDATE_ZIP="$(realpath "$3")"
EXPECTED_APPROVAL="${4:-DRAFT}"
[[ "$EXPECTED_APPROVAL" == "DRAFT" || "$EXPECTED_APPROVAL" == "APPROVED" ]] || { echo "Invalid approval expectation" >&2; exit 2; }

WP_BIN="${WP_CLI_BIN:-wp}"
WP_VERSION_VALUE="${WP_VERSION:-latest}"
DB_HOST_VALUE="${WP_DBDELTA_DB_HOST:-127.0.0.1:3306}"
DB_USER_VALUE="${WP_DBDELTA_DB_USER:-root}"
DB_PASS_VALUE="${WP_DBDELTA_DB_PASSWORD:-root}"
DB_ADMIN_HOST_VALUE="${WP_DBDELTA_DB_ADMIN_HOST:-$DB_HOST_VALUE}"
WORK_ROOT="${WP_DBDELTA_WORK_DIR:-${TMPDIR:-/tmp}/stockland-wp-dbdelta}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEST_PHP="$SCRIPT_DIR/wp-runtime-test.php"

safe_label="$(printf '%s' "$LABEL" | tr '[:upper:]' '[:lower:]' | sed 's/[^a-z0-9]/_/g')"
if [[ ! "$safe_label" =~ ^[a-z0-9_]{1,24}$ ]]; then
  echo "Invalid label: use 1-24 letters, digits, or underscores" >&2
  exit 2
fi
run_tag="$(date -u +%Y%m%d%H%M%S)_$$"
mkdir -p "$WORK_ROOT"
BASE_DB="sl_${safe_label}_${run_tag}_upgrade"
FRESH_DB="sl_${safe_label}_${run_tag}_fresh"
UPGRADE_SITE="$WORK_ROOT/${safe_label}-${run_tag}-upgrade"
FRESH_SITE="$WORK_ROOT/${safe_label}-${run_tag}-fresh"
TEST_ADMIN_PASSWORD="$(php -r 'echo bin2hex(random_bytes(32));')"

create_database() {
  local name="$1"
  local sql_host="${DB_ADMIN_HOST_VALUE%%:*}"
  local sql_port="${DB_ADMIN_HOST_VALUE##*:}"
  if [[ "$sql_port" == "$sql_host" ]]; then sql_port=3306; fi
  MYSQL_PWD="$DB_PASS_VALUE" mysql --protocol=tcp --host="$sql_host" --port="$sql_port" --user="$DB_USER_VALUE" \
    --execute="CREATE DATABASE \`$name\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
}

create_wordpress_site() {
  local dir="$1" db="$2"
  mkdir -p "$dir"
  if [[ "$WP_VERSION_VALUE" == "latest" ]]; then
    "$WP_BIN" core download --path="$dir" --skip-content
  else
    "$WP_BIN" core download --path="$dir" --version="$WP_VERSION_VALUE" --skip-content
  fi
  "$WP_BIN" config create --path="$dir" --dbname="$db" --dbuser="$DB_USER_VALUE" --dbpass="$DB_PASS_VALUE" --dbhost="$DB_HOST_VALUE" --dbprefix=wp_ --skip-check
  "$WP_BIN" config set --path="$dir" WP_DEBUG true --raw
  "$WP_BIN" config set --path="$dir" SAVEQUERIES true --raw
  create_database "$db"
  "$WP_BIN" core install --path="$dir" --url="http://stockland-${safe_label}.test" \
    --title="Stockland isolated ${safe_label} test" --admin_user=stockland_lab_admin \
    --admin_password="$TEST_ADMIN_PASSWORD" --admin_email=stockland-lab@example.invalid --skip-email
}

run_mode() {
  local site="$1" mode="$2"
  echo "===== ${safe_label}: ${mode} (${site}) ====="
  STOCKLAND_EXPECT_COMPENSATION_APPROVAL="$EXPECTED_APPROVAL" STOCKLAND_WP_DBDELTA_MODE="$mode" \
    "$WP_BIN" eval-file "$TEST_PHP" --path="$site"
}

for archive in "$BASELINE_ZIP" "$CANDIDATE_ZIP"; do
  [[ -s "$archive" ]] || { echo "Missing installer: $archive" >&2; exit 2; }
  unzip -tq "$archive" >/dev/null
done

printf '\n===== PACKAGE IDENTITY: %s =====\n' "$safe_label"
sha256sum "$BASELINE_ZIP" "$CANDIDATE_ZIP"
printf 'EXPECTED EXISTING-COMPENSATION STATE: %s\n' "$EXPECTED_APPROVAL"

printf '\n===== PHASE 1: 16.1.95 -> CANDIDATE UPGRADE =====\n'
create_wordpress_site "$UPGRADE_SITE" "$BASE_DB"
"$WP_BIN" plugin install "$BASELINE_ZIP" --path="$UPGRADE_SITE" --activate
run_mode "$UPGRADE_SITE" advance
run_mode "$UPGRADE_SITE" assert-baseline
run_mode "$UPGRADE_SITE" dbdelta-idempotency
run_mode "$UPGRADE_SITE" seed-legacy-fixtures

# Update the active plugin in place, as a normal WordPress plugin update.
"$WP_BIN" plugin install "$CANDIDATE_ZIP" --path="$UPGRADE_SITE" --force
"$WP_BIN" plugin is-active stockland-erp-app --path="$UPGRADE_SITE"
run_mode "$UPGRADE_SITE" advance
run_mode "$UPGRADE_SITE" assert-candidate-upgrade

printf '\n===== PHASE 2: CANDIDATE FRESH INSTALL =====\n'
create_wordpress_site "$FRESH_SITE" "$FRESH_DB"
"$WP_BIN" plugin install "$CANDIDATE_ZIP" --path="$FRESH_SITE" --activate
run_mode "$FRESH_SITE" advance
run_mode "$FRESH_SITE" assert-candidate-fresh

unset TEST_ADMIN_PASSWORD
echo "WP + dbDelta test pair complete: $safe_label"
