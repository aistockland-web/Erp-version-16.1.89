#!/usr/bin/env bash
# Build the 16.2.18 master installer from src/ and verify it on the disposable dev-container WordPress sites.
# Usage: bash .devcontainer/verify-master-installer.sh [mariadb|mysql8|all]   (default: all)
# Sites are synthetic QA sites; their databases are reset. Never point this at production.
set -euo pipefail
cd /workspaces/stockland-erp 2>/dev/null || cd "$(git rev-parse --show-toplevel)"
TARGET="${1:-all}"
export STOCKLAND_DISPOSABLE_SITE=yes
python3 scripts/release/build_master_installer.py
ZIP="$PWD/STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip"
verify() { # <label> <wp-path>
  echo "=== Master installer verification: $1 ==="
  bash scripts/release/verify_master_installer.sh "$2" "$ZIP"
  if [[ -x scripts/release/e2e/run_all.sh ]]; then bash scripts/release/e2e/run_all.sh "$2"; fi
}
[[ "$TARGET" == "mariadb" || "$TARGET" == "all" ]] && verify "MariaDB 10.11 (port 8080 site)" /var/www/html/site-baseline
[[ "$TARGET" == "mysql8" || "$TARGET" == "all" ]] && verify "MySQL 8.0.46 (port 8082 site)" /var/www/html/site-mysql8
echo "Master installer verification finished."
