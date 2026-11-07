#!/usr/bin/env bash
set -euo pipefail

REPO="aistockland-web/Erp-version-16.1.89"
ARTIFACTS="${STOCKLAND_WP_DBDELTA_ARTIFACT_DIR:-$PWD/.artifacts/wp-dbdelta}"
mkdir -p "$ARTIFACTS"/{baseline-outer,legacy-outer,fixed-outer,baseline,legacy,fixed,logs}

command -v gh >/dev/null 2>&1 || { echo "GitHub CLI is required; rebuild the devcontainer." >&2; exit 2; }
gh auth status >/dev/null 2>&1 || { echo "Authenticate with 'gh auth login'." >&2; exit 2; }

# Pin every source to a specific GitHub Actions run and verify the inner ZIP.
gh run download 40036018031 --repo "$REPO" --name "STOCKLAND-ERP-16.1.95-Cumulative-WordPress-Update" --dir "$ARTIFACTS/baseline-outer"
gh run download 42125671298 --repo "$REPO" --name "STOCKLAND-ERP-16.2.9-HR-PAYROLL-CANDIDATE" --dir "$ARTIFACTS/legacy-outer"
gh run download 42258772425 --repo "$REPO" --name "STOCKLAND-ERP-16.2.9-HR-PAYROLL-CANDIDATE" --dir "$ARTIFACTS/fixed-outer"

extract_installer() {
  local artifact_dir="$1" filename="$2" dest_dir="$3" found
  found="$(find "$artifact_dir" -type f -name "$filename" -print -quit)"
  [[ -s "$found" ]] || { echo "Expected installer missing: $filename under $artifact_dir" >&2; exit 1; }
  cp "$found" "$dest_dir/$filename"
  printf '%s\n' "$dest_dir/$filename"
}

BASELINE="$(extract_installer "$ARTIFACTS/baseline-outer" "STOCKLAND_ERP_16.1.95_CANONICAL_WORDPRESS_UPDATE.zip" "$ARTIFACTS/baseline")"
LEGACY="$(extract_installer "$ARTIFACTS/legacy-outer" "STOCKLAND_ERP_16.2.9_HR_PAYROLL_CANDIDATE.zip" "$ARTIFACTS/legacy")"
FIXED="$(extract_installer "$ARTIFACTS/fixed-outer" "STOCKLAND_ERP_16.2.9_HR_PAYROLL_CANDIDATE.zip" "$ARTIFACTS/fixed")"

echo "=== Verify exact installer hashes ==="
echo "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625  $BASELINE" | sha256sum --check -
echo "f360294feb4aa43f13d43d12a22e55867eda26e0502946dd0d494947277604ca  $LEGACY" | sha256sum --check -
echo "fc22346f6e1fafc9e47a50eba76a2c0846fb9f09407168564e1f295ce69290d8  $FIXED" | sha256sum --check -

export WP_VERSION="${WP_VERSION:-latest}"
export WP_CLI_BIN="${WP_CLI_BIN:-wp}"
export WP_DBDELTA_WORK_DIR="${WP_DBDELTA_WORK_DIR:-/tmp/stockland-wp-dbdelta}"
failures=0

restore_preview() {
  local rc=$?
  if [[ -n "${BASELINE:-}" && -f /var/www/html/wp-load.php ]]; then
    echo "=== Restore private preview to protected 16.1.95 baseline ==="
    "$WP_CLI_BIN" plugin install "$BASELINE" --path=/var/www/html --force --activate || echo "WARNING: preview package restore failed"
    STOCKLAND_WP_DBDELTA_MODE=advance "$WP_CLI_BIN" eval-file ci/wp-dbdelta/wp-runtime-test.php --path=/var/www/html || echo "WARNING: preview setup re-entry failed"
    STOCKLAND_WP_DBDELTA_MODE=assert-baseline "$WP_CLI_BIN" eval-file ci/wp-dbdelta/wp-runtime-test.php --path=/var/www/html || echo "WARNING: preview baseline assertion failed"
  fi
  exit "$rc"
}
trap restore_preview EXIT

run_pair() {
  local label="$1" candidate="$2" expected="$3" engine_host="$4" engine_password="$5" engine="$6" log="$7"
  local rc=0
  echo "===== $label on $engine ====="
  WP_DBDELTA_DB_HOST="$engine_host" \
  WP_DBDELTA_DB_ADMIN_HOST="$engine_host" \
  WP_DBDELTA_DB_USER=root \
  WP_DBDELTA_DB_PASSWORD="$engine_password" \
    bash ci/wp-dbdelta/run-pair.sh "$label" "$BASELINE" "$candidate" "$expected" 2>&1 | tee "$log" || rc="${PIPESTATUS[0]}"
  if [[ "$rc" -ne 0 ]]; then
    echo "FAIL: $label on $engine (exit $rc); see $log"
    failures=$((failures+1))
  else
    echo "PASS: $label on $engine"
  fi
}

# Historical package proves the unsafe approval migration was reproducible.
# It is characterization only, not a candidate for release.
run_pair "legacy_approval_defect" "$LEGACY" "APPROVED" "db-mariadb:3306" "root-dev-only" "MariaDB 10.11" "$ARTIFACTS/logs/legacy-mariadb.log"

# Corrected artifact must retain DRAFT and no approval actor/time.
run_pair "corrected_approval_draft" "$FIXED" "DRAFT" "db-mariadb:3306" "root-dev-only" "MariaDB 10.11" "$ARTIFACTS/logs/fixed-mariadb.log"

# MySQL 8 compatibility remains a separate mandatory lane. The known baseline
# nullable composite-primary-key issue is expected to fail before candidate migration.
run_pair "corrected_mysql8" "$FIXED" "DRAFT" "db-mysql:3306" "mysql-dev-only" "MySQL 8.0.46" "$ARTIFACTS/logs/fixed-mysql8.log"

echo "Runtime scenario failures: $failures"
echo "The original 16.2.13 operational source is not recovered; its run state remains NOT RUN."
if [[ "$failures" -ne 0 ]]; then
  exit 1
fi
