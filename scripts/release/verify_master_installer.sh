#!/usr/bin/env bash
# Usage: verify_master_installer.sh <wp-path> <installer.zip>
# Resets the WordPress DB of a DISPOSABLE site, installs the ZIP through WordPress, activates once, verifies.
set -euo pipefail
WP_PATH="${1:?wordpress path}"; ZIP="${2:?installer zip}"; HERE="$(cd "$(dirname "$0")" && pwd)"
WP="wp --path=$WP_PATH --allow-root"
[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || { echo "Refusing: set STOCKLAND_DISPOSABLE_SITE=yes to confirm this is a throwaway site"; exit 2; }
$WP db reset --yes
$WP core install --url=http://localhost --title=Stockland-QA --admin_user=admin --admin_password="$(openssl rand -hex 12)" --admin_email=qa@example.com --skip-email
rm -rf "$WP_PATH/wp-content/plugins/stockland-erp-app"
$WP --user=admin plugin install "$ZIP" --activate
$WP --user=admin eval-file "$HERE/verify_master_installer.php"
