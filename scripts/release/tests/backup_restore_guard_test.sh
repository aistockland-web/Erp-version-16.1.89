#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
cat > "$TMP/wp" <<'SH'
#!/usr/bin/env bash
case " $* " in
  *" config get DB_NAME "*) echo "${FAKE_DB_NAME:-wp}" ;;
  *" config get DB_HOST "*) echo "${FAKE_DB_HOST:-127.0.0.1}" ;;
  *) touch "${WP_SIDE_EFFECT_MARKER:?}" ;;
esac
SH
cat > "$TMP/mysql" <<'SH'
#!/usr/bin/env bash
touch "${MYSQL_SIDE_EFFECT_MARKER:?}"
exit 0
SH
chmod +x "$TMP/wp" "$TMP/mysql"
export PATH="$TMP:$PATH"
export WP_SIDE_EFFECT_MARKER="$TMP/wp-side-effect"
export MYSQL_SIDE_EFFECT_MARKER="$TMP/mysql-side-effect"

run_guard() {
  local label="$1"; shift
  rm -f "$WP_SIDE_EFFECT_MARKER" "$MYSQL_SIDE_EFFECT_MARKER"
  local rc=0
  set +e
  env STOCKLAND_DISPOSABLE_SITE=yes "$@" bash "$ROOT/backup_restore_check.sh" /tmp/fake "$TMP/out" >/dev/null 2>&1
  rc=$?
  set -e
  if [[ "$rc" -eq 0 ]]; then
    echo "FAIL $label was accepted by backup/restore guard"
    return 1
  fi
  if [[ -e "$WP_SIDE_EFFECT_MARKER" || -e "$MYSQL_SIDE_EFFECT_MARKER" ]]; then
    echo "FAIL $label was refused only after a database side effect"
    return 1
  fi
  echo "PASS $label refused before export or MySQL access"
}
run_guard "wrong disposable database name" \
  FAKE_DB_NAME=wp FAKE_DB_HOST=127.0.0.1 \
  STOCKLAND_DISPOSABLE_DB_NAME=wrong STOCKLAND_DISPOSABLE_DB_HOST=127.0.0.1 \
  MYSQL_ADMIN_ARGS="-h127.0.0.1 -uroot"
run_guard "remote WordPress database host" \
  FAKE_DB_NAME=wp FAKE_DB_HOST=db.example.com \
  STOCKLAND_DISPOSABLE_DB_NAME=wp STOCKLAND_DISPOSABLE_DB_HOST=127.0.0.1 \
  MYSQL_ADMIN_ARGS="-h127.0.0.1 -uroot"
run_guard "remote administrative MySQL host" \
  FAKE_DB_NAME=wp FAKE_DB_HOST=127.0.0.1 \
  STOCKLAND_DISPOSABLE_DB_NAME=wp STOCKLAND_DISPOSABLE_DB_HOST=127.0.0.1 \
  MYSQL_ADMIN_ARGS="-hdb.example.com -uroot"
echo "RESULT: PASS"
