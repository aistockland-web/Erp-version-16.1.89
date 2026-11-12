#!/usr/bin/env bash
# Synthetic-data business workflow regression on a DISPOSABLE site already running the master installer.
# Usage: STOCKLAND_DISPOSABLE_SITE=yes run_all.sh <wp-path>
set -euo pipefail
[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || { echo "Refusing: disposable site only"; exit 2; }
H="$(cd "$(dirname "$0")" && pwd)"; WP="wp --path=${1:?wp path} --allow-root"
for s in s1_users_product s1b_sync_roles s2_procurement_receipt s3_transfer s4_sales_return_close s5_accounting_ap s6_customer_invoice s7_payroll_calculator_vectors s8_transfer_notifications; do
  echo "######## $s"; $WP eval-file "$H/$s.php"
done
