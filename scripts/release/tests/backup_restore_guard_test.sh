#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cat > "$TMP/wp" <<'SH'
#!/usr/bin/env bash
case "${3:-} ${4:-} ${5:-}" in
  "config get DB_NAME") echo "${FAKE_DB_NAME:-wp}" ;;
  "config get DB_HOST") echo "${FAKE_DB_HOST:-127.0.0.1}" ;;
  *) touch "${WP_SIDE_EFFECT_MARKER:?}" ;;
esac
SH
cat > "$TMP/mysql" <<'SH'
#!/usr/bin/env bash
touch "${MYSQL_SIDE_EFFECT_MARKER:?}"
exit 0
SH
chmod +x "$TMP/wp" "$TMP/mysql"
export PATH="$TMP:$PATH" WP_SIDE_EFFECT_MARKER="$TMP/wp-side-effect" MYSQL_SIDE_EFFECT_MARKER="$TMP/mysql-side-effect"
run_guard() {
  rm -f "$WP_SIDE_EFFECT_MARKER" "$MYSQL_SIDE_EFFECT_MARKER"
  set +e
  STOCKLAND_DISPOSABLE_SITE=yes "$@" bash "$ROOT/backup_restore_check.sh" /tmp/fake "$TMP/out" >/dev/null 2>&1
  rc=$?
  set -e
  [[ $rc -ne 0 && ! -e "$WP_SIDE_EFFECT_MARKER" && ! -e "$MYSQL_SIDE_EFFECT_MARKER" ]]
}
export FAKE_DB_NAME=wp FAKE_DB_HOST=127.0.0.1 STOCKLAND_DISPOSABLE_DB_NAME=wrong STOCKLAND_DISPOSABLE_DB_HOST=127.0.0.1 MYSQL_ADMIN_ARGS="-h127.0.0.1 -uroot"
run_guard && echo "PASS wrong database name refused before export or mysql"
export STOCKLAND_DISPOSABLE_DB_NAME=wp FAKE_DB_HOST=db.example.com
run_guard && echo "PASS remote WordPress DB host refused before export or mysql"
export FAKE_DB_HOST=127.0.0.1 MYSQL_ADMIN_ARGS="-hdb.example.com -uroot"
run_guard && echo "PASS remote admin DB host refused before mysql"
echo "RESULT: PASS"
