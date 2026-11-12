#!/usr/bin/env bash
# Build the 16.2.18 master installer from src/ and verify it on disposable dev-container WordPress sites.
# Usage: bash .devcontainer/verify-master-installer.sh [mariadb|mysql8|all]   (default: all)
# The target is validated before building or touching any WordPress database.
set -euo pipefail

usage() {
  echo "Usage: bash .devcontainer/verify-master-installer.sh [mariadb|mysql8|all]" >&2
  echo "  mariadb  Verify the disposable MariaDB 10.11 baseline site." >&2
  echo "  mysql8   Verify the disposable MySQL 8.0.46 site." >&2
  echo "  all      Verify both disposable sites (default)." >&2
}

if (( $# > 1 )); then
  usage
  exit 2
fi

TARGET="${1:-all}"
case "$TARGET" in
  mariadb|mysql8|all) ;;
  *)
    echo "Unsupported verification target: $TARGET" >&2
    usage
    exit 2
    ;;
esac

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

# This wrapper is intended for the disposable QA sites declared in the Stockland
# devcontainer Compose file. The inner verifier also requires this explicit marker.
export STOCKLAND_DISPOSABLE_SITE=yes

python3 scripts/release/build_master_installer.py
ZIP="$PWD/STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip"
[[ -s "$ZIP" ]] || { echo "FAIL: master installer ZIP was not created: $ZIP" >&2; exit 1; }

verify() { # <label> <wp-path>
  local label="$1"
  local wp_path="$2"
  echo "=== Master installer verification: $label ==="
  bash scripts/release/verify_master_installer.sh "$wp_path" "$ZIP"
  bash scripts/release/e2e/run_all.sh "$wp_path"
}

case "$TARGET" in
  mariadb)
    verify "MariaDB 10.11 (port 8080 site)" /var/www/html/site-baseline
    ;;
  mysql8)
    verify "MySQL 8.0.46 (port 8082 site)" /var/www/html/site-mysql8
    ;;
  all)
    verify "MariaDB 10.11 (port 8080 site)" /var/www/html/site-baseline
    verify "MySQL 8.0.46 (port 8082 site)" /var/www/html/site-mysql8
    ;;
esac

echo "PASS master installer verification completed for target: $TARGET"
