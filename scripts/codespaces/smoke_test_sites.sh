#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

source "$REPO_ROOT/scripts/codespaces/wp_cli_wrapper.sh"

smoke_site() {
  local site="$1"
  local service="$2"
  local host_header="$3"
  local expected="$4"
  local version

  wp_as_www --path="$site" core is-installed --skip-plugins --skip-themes
  wp_as_www --path="$site" plugin is-active stockland-erp-app
  version="$(wp_as_www --path="$site" plugin get stockland-erp-app --field=version)"
  [[ "$version" == "$expected" ]] || { echo "Expected $expected at $site; found $version" >&2; return 1; }

  curl --fail --silent --show-error --retry 10 --retry-all-errors \
    -H "Host: $host_header" "http://$service/wp-login.php" >/dev/null
  curl --fail --silent --show-error --retry 10 --retry-all-errors \
    -H "Host: $host_header" "http://$service/wp-json/" \
    | python3 "$REPO_ROOT/ci/codespaces/check_rest_index.py"

  echo "PASS WordPress login, Stockland REST contract and plugin $version at $site"
}

smoke_site "/var/www/html/site-baseline" "wordpress_baseline" "localhost:8080" "16.2.13"
smoke_site "/var/www/html/site-upgrade" "wordpress_upgrade" "localhost:8081" "16.2.15"

if [[ -f /var/www/html/site-mysql8/wp-config.php ]]; then
  smoke_site "/var/www/html/site-mysql8" "wordpress_mysql8" "localhost:8082" "16.2.15"
fi

echo "PASS isolated Stockland WordPress smoke checks."
