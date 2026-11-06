#!/usr/bin/env bash
set -euo pipefail
REPO="aistockland-web/Erp-version-16.1.89"
ARTIFACTS="\${STOCKLAND_WP_DBDELTA_ARTIFACT_DIR:-$PWD/.artifacts/wp-dbdelta}"
mkdir -p "$ARTIFACTS/baseline-outer" "$ARTIFACTS/selected-outer" "$ARTIFACTS/refreshed-outer"
command -v gh >/dev/null 2>&1 || { echo "GitHub CLI (gh) is required; rebuild the devcontainer." >&2; exit 2; }
gh auth status >/dev/null 2>&1 || { echo "Authenticate with gh auth login." >&2; exit 2; }
gh run download 40036018031 --repo "$REPO" --name "STOCKLAND-ERP-16.1.95-Cumulative-WordPress-Update" --dir "$ARTIFACTS/baseline-outer"
gh run download 41146685514 --repo "$REPO" --name "STOCKLAND-ERP-16.2.9-HR-PAYROLL-CANDIDATE" --dir "$ARTIFACTS/selected-outer"
gh run download 42125671298 --repo "$REPO" --name "STOCKLAND-ERP-16.2.9-HR-PAYROLL-CANDIDATE" --dir "$ARTIFACTS/refreshed-outer"
extract_inner() {
  local outer_dir="$1" inner_name="$2" target_dir="$3" outer inner
  outer="$(find "$outer_dir" -maxdepth 1 -type f -name '*.zip' -print -quit)"
  [[ -n "$outer" ]] || { echo "No artifact ZIP found under $outer_dir" >&2; exit 1; }
  mkdir -p "$target_dir"; unzip -oq "$outer" -d "$target_dir"
  inner="$(find "$target_dir" -type f -name "$inner_name" -print -quit)"
  [[ -n "$inner" ]] || { echo "Expected installer $inner_name missing from $outer" >&2; exit 1; }
  printf '%s\n' "$inner"
}
BASELINE="$(extract_inner "$ARTIFACTS/baseline-outer" 'STOCKLAND_ERP_16.1.95_CANONICAL_WORDPRESS_UPDATE.zip' "$ARTIFACTS/baseline")"
SELECTED="$(extract_inner "$ARTIFACTS/selected-outer" 'STOCKLAND_ERP_16.2.9_HR_PAYROLL_CANDIDATE.zip' "$ARTIFACTS/selected")"
REFRESHED="$(extract_inner "$ARTIFACTS/refreshed-outer" 'STOCKLAND_ERP_16.2.9_HR_PAYROLL_CANDIDATE.zip' "$ARTIFACTS/refreshed")"
python3 ci/wp-dbdelta/compare-candidates.py "$SELECTED" "$REFRESHED"
export WP_VERSION=latest WP_CLI_BIN=wp
export WP_DBDELTA_DB_HOST="\${WP_DBDELTA_DB_HOST:-db:3306}"
export WP_DBDELTA_DB_ADMIN_HOST="\${WP_DBDELTA_DB_ADMIN_HOST:-$WP_DBDELTA_DB_HOST}"
export WP_DBDELTA_DB_USER="\${WP_DBDELTA_DB_USER:-root}"
export WP_DBDELTA_DB_PASSWORD="\${WP_DBDELTA_DB_PASSWORD:-root-dev-only}"
export WP_DBDELTA_WORK_DIR="\${WP_DBDELTA_WORK_DIR:-/tmp/stockland-wp-dbdelta-codespaces}"
bash ci/wp-dbdelta/run-pair.sh selected "$BASELINE" "$SELECTED" | tee "$ARTIFACTS/selected-runtime.log"
bash ci/wp-dbdelta/run-pair.sh refreshed "$BASELINE" "$REFRESHED" | tee "$ARTIFACTS/refreshed-runtime.log"
# Browser-facing site remains on protected 16.1.95 after isolated candidate tests.
wp plugin install "$BASELINE" --path=/var/www/html --force --activate
STOCKLAND_WP_DBDELTA_MODE=advance wp eval-file ci/wp-dbdelta/wp-runtime-test.php --path=/var/www/html
STOCKLAND_WP_DBDELTA_MODE=assert-baseline wp eval-file ci/wp-dbdelta/wp-runtime-test.php --path=/var/www/html
echo "Codespaces test matrix completed. Browser-facing WordPress remains on protected 16.1.95."
echo "Review logs under: $ARTIFACTS"
