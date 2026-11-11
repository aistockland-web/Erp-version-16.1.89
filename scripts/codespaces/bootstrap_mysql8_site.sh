#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

SITE="/var/www/html/site-mysql8"
BASELINE_PACKAGE="$REPO_ROOT/STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip"
UPDATE_PACKAGE="${STOCKLAND_DEV_16215_PACKAGE_PATH:-$REPO_ROOT/STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip}"
ARTIFACT_DIR="$REPO_ROOT/artifacts/codespaces"
SNAPSHOT="$ARTIFACT_DIR/mysql8-before-16.2.15.sql"
RECEIPT="$ARTIFACT_DIR/mysql8-upgrade-result.txt"
MARKER="stockland_codespaces_upgrade_marker"
MARKER_VALUE="created-on-mysql8-before-16.2.15"

for package in "$BASELINE_PACKAGE" "$UPDATE_PACKAGE"; do
  [[ -s "$package" ]] || { echo "Missing candidate archive: $package" >&2; exit 1; }
done

mkdir -p "$ARTIFACT_DIR"
sudo chown "www-data:$(id -gn)" "$ARTIFACT_DIR"
chmod 0770 "$ARTIFACT_DIR"

source "$REPO_ROOT/scripts/codespaces/wp_cli_wrapper.sh"

for attempt in $(seq 1 120); do
  if [[ -f "$SITE/wp-config.php" && -f "$SITE/wp-load.php" ]]; then
    break
  fi
  sleep 2
done
[[ -f "$SITE/wp-config.php" ]] || { echo "MySQL 8 WordPress did not initialize." >&2; exit 1; }

if ! wp_as_www --path="$SITE" core is-installed --skip-plugins --skip-themes >/dev/null 2>&1; then
  wp_as_www --path="$SITE" core install \
    --url="http://localhost:8082" \
    --title="Stockland ERP MySQL 8.0.46 isolated upgrade QA" \
    --admin_user="$STOCKLAND_DEV_WP_ADMIN_USER" \
    --admin_password="$STOCKLAND_DEV_WP_ADMIN_PASSWORD" \
    --admin_email="qa@example.invalid" --skip-email
fi

if ! wp_as_www --path="$SITE" plugin is-installed stockland-erp-app >/dev/null 2>&1; then
  echo "Install the operational 16.2.13 baseline on MySQL 8.0.46"
  wp_as_www --path="$SITE" plugin install "$BASELINE_PACKAGE" --activate
fi

BEFORE="$(wp_as_www --path="$SITE" plugin get stockland-erp-app --field=version)"
case "$BEFORE" in
  16.2.13)
    echo "Run the protected 16.2.13 setup wizard before the MySQL 8.0.46 update"
    bash scripts/codespaces/advance_setup_wizard.sh "$SITE" "mysql8-16213-before-upgrade"
    BEFORE_FINGERPRINT="$ARTIFACT_DIR/mysql8-16213-before-upgrade-schema.json"
    AFTER_FINGERPRINT="$ARTIFACT_DIR/mysql8-16215-after-upgrade-schema.json"
    COMPARISON="$ARTIFACT_DIR/mysql8-schema-upgrade-comparison.json"
    [[ -s "$BEFORE_FINGERPRINT" ]] || { echo "MySQL 8 pre-upgrade schema fingerprint missing." >&2; exit 1; }
    echo "Upgrade MySQL 8.0.46 from 16.2.13 to repaired 16.2.15"
    wp_as_www --path="$SITE" option update "$MARKER" "$MARKER_VALUE" >/dev/null
    rm -f "$SNAPSHOT"
    wp_as_www --path="$SITE" db export "$SNAPSHOT"
    [[ -s "$SNAPSHOT" ]] || { echo "MySQL 8 pre-upgrade SQL snapshot was not created." >&2; exit 1; }
    SNAPSHOT_SHA="$(sha256sum "$SNAPSHOT" | awk '{print $1}')"
    TABLES_BEFORE="$(wp_as_www --path="$SITE" db query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();" --skip-column-names | tr -d '\r')"
    wp_as_www --path="$SITE" plugin install "$UPDATE_PACKAGE" --force --activate
    wp_as_www --path="$SITE" plugin is-active stockland-erp-app
    AFTER_VERSION="$(wp_as_www --path="$SITE" plugin get stockland-erp-app --field=version)"
    [[ "$AFTER_VERSION" == "16.2.15" ]] || { echo "MySQL 8 expected 16.2.15, found $AFTER_VERSION" >&2; exit 1; }
    bash scripts/codespaces/advance_setup_wizard.sh "$SITE" "mysql8-16215-after-upgrade"
    python3 scripts/codespaces/compare_schema_fingerprints.py "$BEFORE_FINGERPRINT" "$AFTER_FINGERPRINT" --output "$COMPARISON"
    SCHEMA_COMPARISON_STATUS="PASS"
    SNAPSHOT_STATUS="PASS"
    ;;
  16.2.15)
    echo "MySQL 8 site is already at 16.2.15; refusing an automatic downgrade."
    MARKER_CURRENT="$(wp_as_www --path="$SITE" option get "$MARKER" 2>/dev/null || true)"
    [[ "$MARKER_CURRENT" == "$MARKER_VALUE" ]] || {
      echo "Site is already at 16.2.15 without the upgrade marker. Recreate only this disposable QA volume to exercise a clean .13-to-.15 update." >&2
      exit 1
    }
    SNAPSHOT_SHA="NOT_CREATED_ALREADY_AT_16.2.15"
    TABLES_BEFORE="NOT_MEASURED_ALREADY_AT_16.2.15"
    SNAPSHOT_STATUS="NOT_RUN_ALREADY_AT_16.2.15"
    SCHEMA_COMPARISON_STATUS="NOT_RUN_ALREADY_AT_16.2.15"
    ;;
  *)
    echo "Unexpected MySQL 8 plugin version '$BEFORE'; refusing to overwrite it." >&2
    exit 1
    ;;
esac

wp_as_www --path="$SITE" plugin is-active stockland-erp-app
AFTER="$(wp_as_www --path="$SITE" plugin get stockland-erp-app --field=version)"
[[ "$AFTER" == "16.2.15" ]] || { echo "MySQL 8 expected 16.2.15, found $AFTER" >&2; exit 1; }

MARKER_AFTER="$(wp_as_www --path="$SITE" option get "$MARKER" 2>/dev/null || true)"
[[ "$MARKER_AFTER" == "$MARKER_VALUE" ]] || { echo "MySQL 8 synthetic upgrade marker was not preserved." >&2; exit 1; }

DB_VERSION="$(wp_as_www --path="$SITE" db query "SELECT VERSION();" --skip-column-names | tr -d '\r')"
TABLES_AFTER="$(wp_as_www --path="$SITE" db query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();" --skip-column-names | tr -d '\r')"

{
  printf 'database_engine=%s\n' "$DB_VERSION"
  printf 'plugin_version_before=%s\n' "$BEFORE"
  printf 'plugin_version_after=%s\n' "$AFTER"
  printf 'plugin_active=true\n'
  printf 'synthetic_option_preserved=true\n'
  printf 'tables_before=%s\n' "$TABLES_BEFORE"
  printf 'tables_after=%s\n' "$TABLES_AFTER"
  printf 'pre_update_snapshot=%s\n' "$SNAPSHOT_STATUS"
  printf 'pre_update_snapshot_sha256=%s\n' "$SNAPSHOT_SHA"
  printf 'schema_comparison=%s\n' "$SCHEMA_COMPARISON_STATUS"
  printf 'baseline_package_sha256=%s\n' "$(sha256sum "$BASELINE_PACKAGE" | awk '{print $1}')"
  printf 'repaired_candidate_sha256=%s\n' "$(sha256sum "$UPDATE_PACKAGE" | awk '{print $1}')"
} > "$RECEIPT"

echo "PASS MySQL 8 WordPress upgrade state: $BEFORE -> $AFTER; DB $DB_VERSION; receipt $RECEIPT"
