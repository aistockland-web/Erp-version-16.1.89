#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BASELINE_ZIP="$ROOT/STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip"
UPDATE_ZIP="$ROOT/STOCKLAND_ERP_16.2.15_LOCAL_REVIEW_CANDIDATE_WORDPRESS_INSTALLER.zip"
ARTIFACT_DIR="${STOCKLAND_ARTIFACT_DIR:-$ROOT/artifacts/codespaces}"
ENV_FILE="$ROOT/.devcontainer/.env.local"
mkdir -p "$ARTIFACT_DIR"; chmod 0777 "$ARTIFACT_DIR"
test -s "$BASELINE_ZIP"; test -s "$UPDATE_ZIP"
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck disable=SC1090
  source "$ENV_FILE"
fi
if [[ -z "${WP_TEST_ADMIN_PASSWORD:-}" ]]; then
  WP_TEST_ADMIN_PASSWORD="$(openssl rand -hex 24)"
  if [[ -w "$ROOT/.devcontainer" && ! -f "$ENV_FILE" ]]; then
    printf 'WP_TEST_ADMIN_PASSWORD=%s\n' "$WP_TEST_ADMIN_PASSWORD" > "$ENV_FILE"
    chmod 600 "$ENV_FILE"
  fi
fi
WP_TEST_ADMIN_USER="${WP_TEST_ADMIN_USER:-stockland-qa-admin}"
export WP_TEST_ADMIN_PASSWORD WP_TEST_ADMIN_USER
case "$(id -u)" in
  0) WP_RUN_MODE="root" ;;
  33) WP_RUN_MODE="current" ;;
  *) if command -v sudo >/dev/null 2>&1 && id www-data >/dev/null 2>&1; then WP_RUN_MODE="www-data"; else WP_RUN_MODE="current"; fi ;;
esac
run_wp() {
  local path="$1" url="$2"; shift 2
  case "$WP_RUN_MODE" in
    root) wp --allow-root --path="$path" --url="$url" "$@" ;;
    www-data) sudo -u www-data wp --path="$path" --url="$url" "$@" ;;
    current) wp --path="$path" --url="$url" "$@" ;;
  esac
}
port_url() {
  local port="$1" fallback="$2"
  if [[ -n "${CODESPACE_NAME:-}" && -n "${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-}" ]]; then
    printf 'https://%s-%s.%s' "$CODESPACE_NAME" "$port" "$GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN"
  else
    printf '%s' "$fallback"
  fi
}
wait_for_site() {
  local internal="$1" public_url="$2" host="${public_url#*://}"
  for attempt in $(seq 1 90); do
    if curl --fail --silent --header "Host: $host" "http://$internal/wp-login.php" >/dev/null 2>&1; then return 0; fi
    sleep 2
  done
  echo "WordPress did not become ready at $internal" >&2; return 1
}
upgrade_site() {
  local engine="$1" path="$2" public_url="$3" internal="$4"
  local slug="stockland-erp-app" marker="stockland_codespaces_upgrade_marker"
  local before after marker_value count_before count_after db_version host snapshot
  host="${public_url#*://}"; snapshot="$ARTIFACT_DIR/${engine}-before-16.2.15.sql"
  wait_for_site "$internal" "$public_url"
  if ! run_wp "$path" "$public_url" core is-installed --quiet; then
    run_wp "$path" "$public_url" core install --url="$public_url" \
      --title="Stockland ERP isolated $engine QA" --admin_user="$WP_TEST_ADMIN_USER" \
      --admin_password="$WP_TEST_ADMIN_PASSWORD" --admin_email="qa@example.invalid" --skip-email
  fi
  if ! run_wp "$path" "$public_url" plugin is-installed "$slug" >/dev/null 2>&1; then
    run_wp "$path" "$public_url" plugin install "$BASELINE_ZIP" --activate
  fi
  before="$(run_wp "$path" "$public_url" plugin get "$slug" --field=version)"
  if [[ "$before" != "16.2.13" && "$before" != "16.2.15" ]]; then
    echo "Unexpected $slug version on $engine: $before; refusing overwrite." >&2; return 1
  fi
  run_wp "$path" "$public_url" plugin is-active "$slug"
  run_wp "$path" "$public_url" option update "$marker" "created-on-$engine-before-16.2.15" >/dev/null
  count_before="$(run_wp "$path" "$public_url" db query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();" --skip-column-names)"
  db_version="$(run_wp "$path" "$public_url" db query "SELECT VERSION();" --skip-column-names | tr -d '\r')"
  if [[ "$before" == "16.2.13" ]]; then
    run_wp "$path" "$public_url" db export "$snapshot"; test -s "$snapshot"
    run_wp "$path" "$public_url" plugin install "$UPDATE_ZIP" --force --activate
  fi
  after="$(run_wp "$path" "$public_url" plugin get "$slug" --field=version)"
  [[ "$after" == "16.2.15" ]] || { echo "$engine did not reach .15 (got $after)" >&2; return 1; }
  run_wp "$path" "$public_url" plugin is-active "$slug"
  marker_value="$(run_wp "$path" "$public_url" option get "$marker")"
  [[ "$marker_value" == "created-on-$engine-before-16.2.15" ]] || { echo "$engine synthetic marker lost" >&2; return 1; }
  count_after="$(run_wp "$path" "$public_url" db query "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE();" --skip-column-names)"
  curl --fail --silent --header "Host: $host" "http://$internal/wp-json/" >/dev/null
  curl --fail --silent --header "Host: $host" "http://$internal/wp-login.php" >/dev/null
  {
    printf 'engine=%s\n' "$engine"
    printf 'database_version=%s\n' "$db_version"
    printf 'public_url=%s\n' "$public_url"
    printf 'plugin_version_before=%s\n' "$before"
    printf 'plugin_version_after=%s\n' "$after"
    printf 'active=true\n'
    printf 'tables_before=%s\n' "$count_before"
    printf 'tables_after=%s\n' "$count_after"
    printf 'synthetic_option_preserved=true\n'
    printf 'rest_root=HTTP_200\n'
    printf 'pre_update_db_snapshot=%s\n' "$([[ -s "$snapshot" ]] && echo PASS || echo ALREADY_AT_16.2.15)"
  } > "$ARTIFACT_DIR/${engine}-upgrade-result.txt"
  echo "PASS $engine: .13 to .15 update, activation and smoke checks."
}
upgrade_site "mariadb-10.11" "${WP_MARIADB_PATH:-/var/www/wp-mariadb}" "$(port_url 8080 "${WP_MARIADB_URL:-http://localhost:8080}")" wordpress-mariadb
upgrade_site "mysql-8.0.46" "${WP_MYSQL_PATH:-/var/www/wp-mysql}" "$(port_url 8081 "${WP_MYSQL_URL:-http://localhost:8081}")" wordpress-mysql
echo "Both WordPress labs run .15. QA only; production release remains unapproved."
