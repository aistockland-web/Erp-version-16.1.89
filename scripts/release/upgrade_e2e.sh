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
