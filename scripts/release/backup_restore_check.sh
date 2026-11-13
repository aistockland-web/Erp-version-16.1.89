#!/usr/bin/env bash
# Backup and restore drill on a DISPOSABLE site: logical dump -> restore into a scratch database -> table-by-table CHECKSUM + row count comparison.
# Usage: STOCKLAND_DISPOSABLE_SITE=yes backup_restore_check.sh <wp-path> [out-dir]
set -euo pipefail
[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || { echo "Refusing: disposable site only"; exit 2; }
EXPECTED_DB="${STOCKLAND_DISPOSABLE_DB_NAME:-}"
EXPECTED_HOST="${STOCKLAND_DISPOSABLE_DB_HOST:-}"
WP="wp --path=${1:?wp path} --allow-root"
DB="$($WP config get DB_NAME 2>/dev/null | tail -1)"
DB_HOST="$($WP config get DB_HOST 2>/dev/null | tail -1)"
[[ -n "$EXPECTED_DB" && "$DB" == "$EXPECTED_DB" ]] || { echo "Refusing: source database name is not the explicitly named disposable database"; exit 2; }
[[ "$EXPECTED_HOST" == "127.0.0.1" && "$DB_HOST" == "127.0.0.1" ]] || { echo "Refusing: WordPress database host must be explicitly loopback (127.0.0.1)"; exit 2; }
[[ "${MYSQL_ADMIN_ARGS:-}" == *"-h127.0.0.1"* || "${MYSQL_ADMIN_ARGS:-}" == *"--host=127.0.0.1"* ]] || { echo "Refusing: administrative MySQL host must be explicitly loopback"; exit 2; }
ADM="mysql ${MYSQL_ADMIN_ARGS:--uroot}"  # administrative account able to CREATE/DROP the scratch database (CI: MYSQL_ADMIN_ARGS="-h127.0.0.1 -uroot -proot")
OUT="${2:-$(mktemp -d)}"; mkdir -p "$OUT"
RST="${DB}_restore_drill"
echo "Source database: $DB  scratch restore database: $RST"
$WP db export "$OUT/backup.sql" --single-transaction >/dev/null 2>&1
sha256sum "$OUT/backup.sql" | tee "$OUT/backup.sql.sha256"
$ADM -e "DROP DATABASE IF EXISTS \`$RST\`; CREATE DATABASE \`$RST\`;"
$ADM "$RST" < "$OUT/backup.sql"
PREFIX="$($WP db prefix | tail -1)"
fail=0; n=0
for t in $($ADM -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' ORDER BY 1"); do
  a="$($ADM -N -e "CHECKSUM TABLE \`$DB\`.\`$t\`" | awk '{print $2}')"; b="$($ADM -N -e "CHECKSUM TABLE \`$RST\`.\`$t\`" | awk '{print $2}')"
  ra="$($ADM -N -e "SELECT COUNT(*) FROM \`$DB\`.\`$t\`")"; rb="$($ADM -N -e "SELECT COUNT(*) FROM \`$RST\`.\`$t\`")"
  n=$((n+1)); if [[ "$a" != "$b" || "$ra" != "$rb" ]]; then echo "FAIL $t source=$a/$ra restored=$b/$rb"; fail=$((fail+1)); fi
done
app="$($ADM -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$RST' AND table_name LIKE '${PREFIX}stock\\_%' ESCAPE '\\\\'")"
rows="$($ADM -N -e "SELECT SUM(table_rows) FROM information_schema.tables WHERE table_schema='$RST'")"
$ADM -e "DROP DATABASE \`$RST\`;"
echo "tables compared: $n, stockland application tables in restore: $app, mismatches: $fail"
[[ $fail -eq 0 && $n -gt 0 ]] && echo "RESULT: PASS" || { echo "RESULT: FAIL"; exit 1; }
