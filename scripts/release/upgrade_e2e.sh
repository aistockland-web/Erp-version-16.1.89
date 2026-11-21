#!/usr/bin/env bash
# Upgrade-path acceptance on a DISPOSABLE WordPress site: 16.2.13 baseline -> wizard in bounded batches -> synthetic business data -> snapshot
# -> in-place upgrade to the master installer -> gated HR migration -> snapshot comparison -> resilience drills.
# Usage: STOCKLAND_DISPOSABLE_SITE=yes upgrade_e2e.sh <wp-path> <baseline.zip> <master.zip> [work-dir]
set -euo pipefail
[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || { echo "Refusing: disposable site only"; exit 2; }
WPPATH="${1:?wp path}"; BASE="${2:?baseline zip}"; MASTER="${3:?master zip}"; WORK="${4:-$(mktemp -d)}"; mkdir -p "$WORK"
HERE="$(cd "$(dirname "$0")" && pwd)"; WP="wp --path=$WPPATH --allow-root"
EXPECTED_BASELINE_SHA="74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13"
ACTUAL_BASELINE_SHA="$(sha256sum "$BASE" | awk '{print $1}')"
if [[ "$ACTUAL_BASELINE_SHA" != "$EXPECTED_BASELINE_SHA" ]]; then
  echo "Refusing unexpected 16.2.13 baseline bytes: $ACTUAL_BASELINE_SHA"
  exit 1
fi
MASTER_DIR="$(cd "$(dirname "$MASTER")" && pwd)"
MASTER_FILE="$(basename "$MASTER")"
(
  cd "$MASTER_DIR"
  sha256sum -c "$MASTER_FILE.sha256"
)
echo "== pinned baseline $ACTUAL_BASELINE_SHA -> verified master $(sha256sum "$MASTER" | awk '{print $1}')"
QA_PASS="${WP_PASS:-$(openssl rand -hex 18)}"
$WP db reset --yes >/dev/null
$WP core install --url="${WP_URL:-http://localhost}" --title=Upgrade-QA --admin_user=admin --admin_password="$QA_PASS" --admin_email=qa@example.com --skip-email >/dev/null
unset QA_PASS

# MySQL 8 cannot create the historical 16.2.13 DDL literally because that DDL
# declares variant_id NULL while also including it in a PRIMARY KEY. An existing
# MySQL table cannot have a nullable PRIMARY KEY member: MySQL materializes that
# member as NOT NULL. The upgrade test must start from that valid *existing-table*
# state, not from an impossible fresh CREATE. Seed only this legacy read-model
# table on disposable MySQL 8; keep the pinned 16.2.13 ZIP byte-for-byte unchanged.
DB_VERSION="$($WP db query 'SELECT VERSION()' --skip-column-names | tr -d '\r')"
if [[ "$DB_VERSION" == 8.0.* ]]; then
  PREFIX="$($WP config get table_prefix)"
  [[ "$PREFIX" =~ ^[A-Za-z0-9_]+$ ]] || { echo "Refusing unexpected WordPress table prefix: $PREFIX"; exit 1; }

  $WP db query "CREATE TABLE IF NOT EXISTS ${PREFIX}stock_balances (
      location_id BIGINT UNSIGNED NOT NULL,
      sku VARCHAR(64) NOT NULL,
      variant_id BIGINT UNSIGNED NOT NULL,
      physical_qty DECIMAL(14,2) NOT NULL DEFAULT 0,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (location_id,variant_id),
      KEY sku (sku),
      KEY location_sku (location_id,sku)
    ) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci;"

  VARIANT_NULLABLE="$($WP db query "SELECT IS_NULLABLE FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='${PREFIX}stock_balances' AND COLUMN_NAME='variant_id'" --skip-column-names | tr -d '\r')"
  PRIMARY_KEY_COLUMNS="$($WP db query "SELECT GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='${PREFIX}stock_balances' AND INDEX_NAME='PRIMARY'" --skip-column-names | tr -d '\r')"
  [[ "$VARIANT_NULLABLE" == "NO" ]] || { echo "MySQL legacy fixture variant_id must be NOT NULL; got '$VARIANT_NULLABLE'"; exit 1; }
  [[ "$PRIMARY_KEY_COLUMNS" == "location_id,variant_id" ]] || { echo "MySQL legacy fixture primary key mismatch: '$PRIMARY_KEY_COLUMNS'"; exit 1; }
  echo "PASS MySQL 8 legacy stock_balances fixture has the valid physical PRIMARY KEY semantics"
fi
rm -rf "$WPPATH/wp-content/plugins/stockland-erp-app"
$WP --user=admin plugin install "$BASE" --activate >/dev/null
echo "== 1 wizard (baseline)"; $WP eval-file "$HERE/setup_wizard_drive.php"
echo "== 2 synthetic business data on the baseline (expected HTTP 4xx/5xx are baseline behaviours, listed for the record)"
for s in s1_users_product s1b_sync_roles s2_procurement_receipt s3_transfer s4_sales_return_close s5_accounting_ap s6_customer_invoice; do $WP eval-file "$HERE/e2e/$s.php" 2>&1 | grep -E "HTTP (4|5)[0-9][0-9]" | sed "s/^/   [$s] /" || true; done
STOCKLAND_SNAPSHOT_OUT="$WORK/before.json" $WP eval-file "$HERE/schema_snapshot.php"
echo "== 3 in-place upgrade"; $WP --user=admin plugin install "$MASTER" --force >/dev/null; $WP plugin list --name=stockland-erp-app --fields=version,status
echo "== 4 gated HR migration"; $WP eval-file "$HERE/upgrade_assert.php"
STOCKLAND_SNAPSHOT_OUT="$WORK/after.json" $WP eval-file "$HERE/schema_snapshot.php"
echo "== 5 snapshot comparison"; python3 "$HERE/compare_snapshots.py" "$WORK/before.json" "$WORK/after.json"
echo "== 6 resilience drills"; $WP eval-file "$HERE/setup_resilience.php"
echo "UPGRADE E2E RESULT: PASS"
