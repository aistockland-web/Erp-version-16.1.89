#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

PACKAGE_16213="$REPO_ROOT/STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip"
PACKAGE_16215="${STOCKLAND_DEV_16215_PACKAGE_PATH:-$REPO_ROOT/STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip}"
ARTIFACT_DIR="$REPO_ROOT/artifacts/codespaces"
BASELINE_SITE="/var/www/html/site-baseline"
UPGRADE_SITE="/var/www/html/site-upgrade"

for package in "$PACKAGE_16213" "$PACKAGE_16215"; do
  [[ -f "$package" ]] || { echo "Missing candidate package: $package" >&2; exit 1; }
done

source "$REPO_ROOT/scripts/codespaces/wp_cli_wrapper.sh"

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
  if ! wp_as_www --path="$site" core is-installed --skip-plugins --skip-themes >/dev/null 2>&1; then
    wp_as_www --path="$site" core install --url="$url" --title="$title" --admin_user="$STOCKLAND_DEV_WP_ADMIN_USER" --admin_password="$STOCKLAND_DEV_WP_ADMIN_PASSWORD" --admin_email="qa@example.invalid" --skip-email
  fi
}

install_local_plugin() {
  local site="$1"
  local archive="$2"
  wp_as_www --path="$site" plugin install "$archive" --force --activate
}

assert_plugin_version() {
  local site="$1"
  local expected="$2"
  local actual
  wp_as_www --path="$site" plugin is-active stockland-erp-app
  actual="$(wp_as_www --path="$site" plugin get stockland-erp-app --field=version)"
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
bash scripts/codespaces/advance_setup_wizard.sh "$BASELINE_SITE" "mariadb-10.11-16213-baseline"

echo "=== MariaDB 10.11 — upgrade path from 16.2.13 to 16.2.15 ==="
install_core_if_needed "$UPGRADE_SITE" "http://localhost:8081" "Stockland ERP 16.2.15 Upgrade QA"
install_local_plugin "$UPGRADE_SITE" "$PACKAGE_16213"
assert_plugin_version "$UPGRADE_SITE" "16.2.13"
bash scripts/codespaces/advance_setup_wizard.sh "$UPGRADE_SITE" "mariadb-10.11-16213-before-upgrade"
wp_as_www --path="$UPGRADE_SITE" option update stockland_codespaces_upgrade_marker created-on-mariadb-before-16.2.15 >/dev/null
SNAPSHOT="$ARTIFACT_DIR/mariadb-before-16.2.15.sql"
wp_as_www --path="$UPGRADE_SITE" db export "$SNAPSHOT"
[[ -s "$SNAPSHOT" ]] || { echo "MariaDB pre-upgrade SQL snapshot missing." >&2; exit 1; }
SNAPSHOT_SHA="$(sha256sum "$SNAPSHOT" | awk '{print $1}')"
BEFORE_FINGERPRINT="$ARTIFACT_DIR/mariadb-10.11-16213-before-upgrade-schema.json"
[[ -s "$BEFORE_FINGERPRINT" ]] || { echo "MariaDB pre-upgrade schema fingerprint missing." >&2; exit 1; }
install_local_plugin "$UPGRADE_SITE" "$PACKAGE_16215"
assert_plugin_version "$UPGRADE_SITE" "16.2.15"
bash scripts/codespaces/advance_setup_wizard.sh "$UPGRADE_SITE" "mariadb-10.11-16215-after-upgrade"
AFTER_FINGERPRINT="$ARTIFACT_DIR/mariadb-10.11-16215-after-upgrade-schema.json"
COMPARISON="$ARTIFACT_DIR/mariadb-10.11-schema-upgrade-comparison.json"
python3 scripts/codespaces/compare_schema_fingerprints.py "$BEFORE_FINGERPRINT" "$AFTER_FINGERPRINT" --output "$COMPARISON"
MARKER_AFTER="$(wp_as_www --path="$UPGRADE_SITE" option get stockland_codespaces_upgrade_marker 2>/dev/null || true)"
[[ "$MARKER_AFTER" == "created-on-mariadb-before-16.2.15" ]] || { echo "MariaDB upgrade marker was not preserved." >&2; exit 1; }
{
  printf 'database_engine=%s\n' "$(wp_as_www --path="$UPGRADE_SITE" db query "SELECT VERSION();" --skip-column-names | tr -d '\r')"
  printf 'plugin_version_before=16.2.13\nplugin_version_after=16.2.15\n'
  printf 'baseline_package_sha256=%s\n' "$(sha256sum "$PACKAGE_16213" | awk '{print $1}')"
  printf 'repaired_candidate_sha256=%s\n' "$(sha256sum "$PACKAGE_16215" | awk '{print $1}')"
  printf 'setup_before=PASS\nsetup_after=PASS\nschema_non_destructive_comparison=PASS\nsynthetic_option_preserved=true\n'
  printf 'pre_update_snapshot_sha256=%s\n' "$SNAPSHOT_SHA"
  printf 'before_schema_fingerprint=%s\nafter_schema_fingerprint=%s\nschema_comparison=%s\n' "$(basename "$BEFORE_FINGERPRINT")" "$(basename "$AFTER_FINGERPRINT")" "$(basename "$COMPARISON")"
} > "$ARTIFACT_DIR/mariadb-upgrade-result.txt"

echo "PASS both isolated MariaDB WordPress sites are initialized."
