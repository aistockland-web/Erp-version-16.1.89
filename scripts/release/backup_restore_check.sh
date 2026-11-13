#!/usr/bin/env bash
# Logical backup/restore integrity drill on an explicitly disposable WordPress site.
# Usage: STOCKLAND_DISPOSABLE_SITE=yes backup_restore_check.sh <wp-path> [out-dir]
set -euo pipefail

[[ "${STOCKLAND_DISPOSABLE_SITE:-}" == "yes" ]] || {
  echo "Refusing: STOCKLAND_DISPOSABLE_SITE=yes is required."
  exit 2
}

WP_PATH="${1:?wp path is required}"
OUT="${2:-$(mktemp -d)}"
mkdir -p "$OUT"

read -r -a MYSQL_ADMIN_FLAGS <<< "${MYSQL_ADMIN_ARGS:--uroot}"
mysql_admin() {
  mysql "${MYSQL_ADMIN_FLAGS[@]}" "$@"
}
wp_site() {
  wp --path="$WP_PATH" --allow-root "$@"
}

EXPECTED_DB="${STOCKLAND_DISPOSABLE_DB_NAME:-}"
EXPECTED_HOST="${STOCKLAND_DISPOSABLE_DB_HOST:-}"
DB="$(wp_site config get DB_NAME 2>/dev/null | tail -n 1)"
ACTUAL_HOST="$(wp_site config get DB_HOST 2>/dev/null | tail -n 1)"
[[ -n "$EXPECTED_DB" && "$DB" == "$EXPECTED_DB" ]] || {
  echo "Refusing: WordPress database name does not match STOCKLAND_DISPOSABLE_DB_NAME."
  exit 2
}
[[ "$EXPECTED_HOST" == "127.0.0.1" && "$ACTUAL_HOST" == "$EXPECTED_HOST" ]] || {
  echo "Refusing: WordPress database host must match the explicitly approved loopback host."
  exit 2
}

ADMIN_HOST=""
for (( i = 0; i < ${#MYSQL_ADMIN_FLAGS[@]}; i++ )); do
  arg="${MYSQL_ADMIN_FLAGS[$i]}"
  case "$arg" in
    -h)
      i=$((i + 1))
      ADMIN_HOST="${MYSQL_ADMIN_FLAGS[$i]:-}"
      ;;
    -h*)
      ADMIN_HOST="${arg#-h}"
      ;;
    --host=*)
      ADMIN_HOST="${arg#--host=}"
      ;;
  esac
done
[[ "$ADMIN_HOST" == "127.0.0.1" ]] || {
  echo "Refusing: MySQL administrative connection must use 127.0.0.1."
  exit 2
}

[[ "$DB" =~ ^[A-Za-z0-9_]+$ ]] || {
  echo "Refusing: the configured database name is not a safe SQL identifier."
  exit 2
}

# A unique scratch name avoids deleting/replacing any pre-existing database.
SUFFIX="${BASHPID}_${RANDOM}"
DB_PREFIX="${DB:0:28}"
RST="${DB_PREFIX}_restore_${SUFFIX}"
[[ "$RST" != "$DB" && ${#RST} -le 64 ]] || {
  echo "Refusing: generated scratch database name is invalid."
  exit 2
}

RESTORE_DB_CREATED=0
cleanup() {
  if [[ "$RESTORE_DB_CREATED" == "1" ]]; then
    mysql_admin -e "DROP DATABASE IF EXISTS `$RST`;" >/dev/null 2>&1 || true
  fi
}
trap cleanup EXIT

echo "Source database: $DB"
echo "Scratch restore database: $RST"

wp_site db export "$OUT/backup.sql" --single-transaction >/dev/null
test -s "$OUT/backup.sql"
sha256sum "$OUT/backup.sql" | tee "$OUT/backup.sql.sha256"

mysql_admin -e "CREATE DATABASE `$RST`;"
RESTORE_DB_CREATED=1
mysql_admin "$RST" < "$OUT/backup.sql"

mapfile -t TABLES < <(mysql_admin -N -B -e "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB' ORDER BY table_name")
[[ ${#TABLES[@]} -gt 0 ]] || {
  echo "FAIL: source database has no tables."
  exit 1
}

fail=0
n=0
for table in "${TABLES[@]}"; do
  [[ "$table" =~ ^[A-Za-z0-9_\$]+$ ]] || {
    echo "FAIL: unsupported table identifier encountered."
    exit 1
  }
  source_checksum="$(mysql_admin -N -e "CHECKSUM TABLE `$DB`.`$table`" | awk 'NR == 1 { print $2 }')"
  restore_checksum="$(mysql_admin -N -e "CHECKSUM TABLE `$RST`.`$table`" | awk 'NR == 1 { print $2 }')"
  source_rows="$(mysql_admin -N -e "SELECT COUNT(*) FROM `$DB`.`$table`")"
  restore_rows="$(mysql_admin -N -e "SELECT COUNT(*) FROM `$RST`.`$table`")"
  n=$((n + 1))
  if [[ "$source_checksum" != "$restore_checksum" || "$source_rows" != "$restore_rows" ]]; then
    echo "FAIL $table source=$source_checksum/$source_rows restored=$restore_checksum/$restore_rows"
    fail=$((fail + 1))
  fi
done

echo "Tables compared: $n; mismatches: $fail"
if [[ "$fail" -eq 0 && "$n" -gt 0 ]]; then
  echo "RESULT: PASS"
else
  echo "RESULT: FAIL"
  exit 1
fi
