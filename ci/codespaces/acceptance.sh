#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"
RUNTIME="$ROOT/.codespaces/runtime"
WP213="$RUNTIME/wordpress/16.2.13"
WPUPGRADE="$RUNTIME/wordpress/upgrade"
CREDS="$RUNTIME/credentials"
REPORT="$RUNTIME/acceptance-report.md"
PLUGINS213="$WP213/wp-content/plugins"
PLUGINSUPGRADE="$WPUPGRADE/wp-content/plugins"
mkdir -p "$WP213" "$WPUPGRADE" "$PLUGINS213" "$PLUGINSUPGRADE" "$CREDS"
chmod 700 "$CREDS"

python3 ci/codespaces/verify_installers.py --verify-all --report "$RUNTIME/installer-verification.json"

plugin_entry_for() {
  python3 - "$RUNTIME/installer-verification.json" "$1" <<'PY'
import json, sys
from pathlib import PurePosixPath
report = json.loads(open(sys.argv[1], encoding="utf-8").read())
package = next(item for item in report["packages"] if item["version"] == sys.argv[2])
entry = PurePosixPath(package["plugin_entry_point"]).relative_to("stockland-erp-app")
print("stockland-erp-app/" + entry.as_posix())
PY
}

PLUGIN_213="$(plugin_entry_for 16.2.13)"
PLUGIN_215="$(plugin_entry_for 16.2.15)"
if [[ "$PLUGIN_213" != "$PLUGIN_215" ]]; then
  echo "Candidate changes the WordPress plugin entry path; refusing an implicit in-place update." >&2
  echo "16.2.13: $PLUGIN_213" >&2
  echo "16.2.15: $PLUGIN_215" >&2
  exit 1
fi

python3 ci/codespaces/verify_installers.py --extract-version 16.2.13 --destination-root "$PLUGINS213"
python3 ci/codespaces/verify_installers.py --extract-version 16.2.13 --destination-root "$PLUGINSUPGRADE"

wait_for_site() {
  local url="$1"
  for ((i=1; i<=60; i++)); do
    local code
    code="$(curl --silent --output /dev/null --write-out '%{http_code}' "$url/wp-login.php" || true)"
    if [[ "$code" =~ ^(200|301|302)$ ]]; then return 0; fi
    sleep 2
  done
  echo "WordPress did not become ready: $url" >&2
  return 1
}

wp213() {
  WORDPRESS_DB_HOST=mariadb-213:3306 WORDPRESS_DB_NAME=stockland_213 \
  WORDPRESS_DB_USER=stockland_dev WORDPRESS_DB_PASSWORD=stockland_local_only \
    wp --path="$WP213" --url="http://localhost:8080" "$@"
}

wpupgrade() {
  WORDPRESS_DB_HOST=mysql-215:3306 WORDPRESS_DB_NAME=stockland_215 \
  WORDPRESS_DB_USER=stockland_dev WORDPRESS_DB_PASSWORD=stockland_local_only \
    wp --path="$WPUPGRADE" --url="http://localhost:8081" "$@"
}

install_sandbox() {
  local command_name="$1" credentials="$2" url="$3"
  if ! "$command_name" core is-installed >/dev/null 2>&1; then
    local password
    password="$(openssl rand -hex 24)"
    "$command_name" core install --url="$url" --title="Stockland ERP isolated QA" \
      --admin_user=stockland_qa_owner --admin_password="$password" \
      --admin_email=stockland-qa@example.invalid --skip-email
    umask 077
    {
      printf 'URL=%s\n' "$url"
      printf 'USERNAME=%s\n' stockland_qa_owner
      printf 'PASSWORD=%s\n' "$password"
    } > "$credentials"
    chmod 600 "$credentials"
    unset password
  fi
}

check_rest() {
  local url="$1" port="$2" label="$3"
  local output="$RUNTIME/rest-$label.json"
  curl --fail --silent --show-error --retry 12 --retry-delay 2 --retry-connrefused \
    --header "Host: localhost:$port" \
    --output "$output" --write-out "WordPress REST HTTP %{http_code}\n" \
    "$url/index.php?rest_route=/"
  if [[ ! -s "$output" ]]; then
    echo "WordPress REST endpoint returned an empty response: $url/index.php?rest_route=/" >&2
    exit 1
  fi
  python3 - "$output" <<'PY'
import json, sys
from pathlib import Path
body = Path(sys.argv[1]).read_text(encoding="utf-8")
try:
    data = json.loads(body)
except json.JSONDecodeError as error:
    raise SystemExit("WordPress REST endpoint did not return JSON: " + str(error) + "; body prefix=" + repr(body[:240]))
namespaces = [str(item).lower() for item in data.get("namespaces", [])]
if not namespaces or not any("stockland" in item for item in namespaces):
    raise SystemExit("Stockland namespace is absent from the WordPress REST index: " + repr(namespaces))
print("PASS WordPress REST index and Stockland namespace")
PY
}

wait_for_site http://wordpress-213
wait_for_site http://wordpress-upgrade

echo "Install/activate 16.2.13 on MariaDB 10.11"
install_sandbox wp213 "$CREDS/wordpress-16.2.13-mariadb.env" http://localhost:8080
wp213 plugin activate "$PLUGIN_213"
wp213 plugin is-active "$PLUGIN_213"
check_rest http://wordpress-213 8080 16.2.13-mariadb

echo "Install/activate 16.2.13 on MySQL 8.0.46"
install_sandbox wpupgrade "$CREDS/wordpress-upgrade-mysql.env" http://localhost:8081
wpupgrade plugin activate "$PLUGIN_213"
wpupgrade plugin is-active "$PLUGIN_213"

echo "Replace plugin files with verified 16.2.15 candidate; retain test database"
python3 ci/codespaces/verify_installers.py --extract-version 16.2.15 --destination-root "$PLUGINSUPGRADE"
wpupgrade plugin is-active "$PLUGIN_215"
check_rest http://wordpress-upgrade 8081 16.2.15-mysql-update

{
  echo "# Stockland ERP Codespaces acceptance"
  echo
  echo "- .13 clean WordPress/MariaDB install and plugin activation: PASS"
  echo "- .13 clean WordPress/MySQL install and plugin activation: PASS"
  echo "- .13-to-.15 candidate file update and plugin boot on MySQL: PASS"
  echo "- WordPress REST index and Stockland namespace on both sites: PASS"
  echo "- Production WordPress/database touched: NO"
  echo "- Full POS/inventory/finance/HR business workflows: NOT RUN by this smoke script"
  echo "- Statutory payroll, SOP and Owner approval: OWNER INPUT REQUIRED"
} > "$REPORT"
echo "PASS: isolated acceptance report written to $REPORT"
