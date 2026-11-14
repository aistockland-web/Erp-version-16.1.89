#!/usr/bin/env bash
# Install and test the exact Owner-approved 16.2.18 Actions artifact.
# No local build/repack step is performed. All WordPress targets must be disposable Compose services.
set -euo pipefail

TARGET=all
if [[ $# -gt 0 ]]; then TARGET="$1"; fi
if [[ $# -gt 1 ]]; then echo "Usage: $0 [mariadb|mysql8|all]" >&2; exit 2; fi
case "$TARGET" in mariadb|mysql8|all) ;; *) echo "Unsupported target: $TARGET" >&2; exit 2 ;; esac

ROOT="$(git rev-parse --show-toplevel)"
cd "$ROOT"
RUN_ID=43570216177
ARTIFACT_NAME=stockland-erp-16.2.18-one-pack-installer
EXPECTED_SHA=de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08
ARTIFACT_DIR="$(mktemp -d /tmp/stockland-approved-16218.XXXXXX)"
trap 'rm -rf "$ARTIFACT_DIR"' EXIT

GH_TOKEN_VALUE="$(printenv GH_TOKEN 2>/dev/null || true)"
if [[ -z "$GH_TOKEN_VALUE" ]] && command -v gh >/dev/null 2>&1; then
  GH_TOKEN_VALUE="$(gh auth token)"
fi
[[ -n "$GH_TOKEN_VALUE" ]] || { echo "Authenticate gh or provide GH_TOKEN to download the approved Actions artifact." >&2; exit 2; }

echo "=== Download exact approved release artifact #14104031868 from run $RUN_ID ==="
ARTIFACT_ID=14104031868
API_URL="https://api.github.com/repos/aistockland-web/Erp-version-16.1.89/actions/artifacts/$ARTIFACT_ID/zip"
DOWNLOAD_URL="$(curl --fail --silent --show-error --output /dev/null --write-out '%{redirect_url}' \
  -H "Accept: application/vnd.github+json" \
  -H "Authorization: Bearer $GH_TOKEN_VALUE" \
  -H "X-GitHub-Api-Version: 2022-11-28" "$API_URL")"
[[ -n "$DOWNLOAD_URL" ]] || { echo "GitHub did not return an artifact download URL." >&2; exit 1; }
OUTER="$ARTIFACT_DIR/approved-actions-artifact.zip"
curl --fail --silent --show-error --location --retry 3 "$DOWNLOAD_URL" --output "$OUTER"
OUTER_SHA="$(sha256sum "$OUTER" | awk '{print $1}')"
[[ "$OUTER_SHA" == "ff58626d39c63980eba7f3de5cf8e2ee7c5788952e746731791fdd20b434d133" ]] || {
  echo "Downloaded Actions wrapper does not match artifact #14104031868." >&2; exit 1;
}
unzip -q "$OUTER" -d "$ARTIFACT_DIR"

ZIP="$ARTIFACT_DIR/STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip"
SIDECAR="$ZIP.sha256"
[[ -s "$ZIP" && -s "$SIDECAR" ]] || { echo "Approved installer or SHA-256 sidecar missing from Actions artifact." >&2; exit 1; }
ACTUAL_SHA="$(sha256sum "$ZIP" | awk '{print $1}')"
[[ "$ACTUAL_SHA" == "$EXPECTED_SHA" ]] || { echo "Wrong installer SHA-256: $ACTUAL_SHA" >&2; exit 1; }
grep -Eq "^$EXPECTED_SHA[[:space:]]+STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip$" "$SIDECAR" || {
  echo "The Actions sidecar does not match the pinned approved installer." >&2; exit 1;
}
python3 ci/codespaces/verify_approved_16_2_18_zip.py "$ZIP"

# WP-CLI is a QA tool only. Download it to the temporary folder and verify its upstream checksum.
if ! command -v wp >/dev/null 2>&1; then
  mkdir -p "$ARTIFACT_DIR/wp-cli"
  curl --fail --silent --show-error --location --retry 3 \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar \
    --output "$ARTIFACT_DIR/wp-cli/wp"
  curl --fail --silent --show-error --location --retry 3 \
    https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar.sha256 \
    --output "$ARTIFACT_DIR/wp-cli/wp.sha256"
  EXPECTED_WP_CLI="$(awk '{print $1}' "$ARTIFACT_DIR/wp-cli/wp.sha256")"
  ACTUAL_WP_CLI="$(sha256sum "$ARTIFACT_DIR/wp-cli/wp" | awk '{print $1}')"
  [[ -n "$EXPECTED_WP_CLI" && "$ACTUAL_WP_CLI" == "$EXPECTED_WP_CLI" ]] || {
    echo "WP-CLI checksum verification failed." >&2; exit 1;
  }
  chmod 0755 "$ARTIFACT_DIR/wp-cli/wp"
  export PATH="$ARTIFACT_DIR/wp-cli:$PATH"
fi

[[ -n "$STOCKLAND_DEV_DB_USER" && -n "$STOCKLAND_DEV_DB_PASSWORD" ]] || {
  echo "Missing local disposable database credentials; run .devcontainer/initialize-host.sh first." >&2; exit 2;
}

verify_site() {
  local label="$1" wp_path="$2" db_host="$3" db_name="$4"
  echo "=== Exact 16.2.18 installer: $label ==="
  export WORDPRESS_DB_HOST="$db_host"
  export WORDPRESS_DB_NAME="$db_name"
  export WORDPRESS_DB_USER="$STOCKLAND_DEV_DB_USER"
  export WORDPRESS_DB_PASSWORD="$STOCKLAND_DEV_DB_PASSWORD"
  export STOCKLAND_DISPOSABLE_SITE=yes
  export STOCKLAND_CODESPACES_COMPOSE=yes
  export STOCKLAND_DISPOSABLE_DB_NAME="$db_name"
  export STOCKLAND_DISPOSABLE_DB_HOST="$db_host"

  bash scripts/release/verify_master_installer.sh "$wp_path" "$ZIP"
  bash scripts/release/e2e/run_all.sh "$wp_path"
  echo "PASS $label — exact approved 16.2.18 artifact, ZIP SHA $EXPECTED_SHA"
}

case "$TARGET" in
  mariadb)
    verify_site "MariaDB 10.11" /var/www/html/site-baseline mariadb:3306 "$STOCKLAND_DEV_DB_BASELINE"
    ;;
  mysql8)
    verify_site "MySQL 8.0.46" /var/www/html/site-mysql8 mysql8:3306 "$STOCKLAND_DEV_MYSQL8_DB_NAME"
    ;;
  all)
    verify_site "MariaDB 10.11" /var/www/html/site-baseline mariadb:3306 "$STOCKLAND_DEV_DB_BASELINE"
    verify_site "MySQL 8.0.46" /var/www/html/site-mysql8 mysql8:3306 "$STOCKLAND_DEV_MYSQL8_DB_NAME"
    ;;
esac

echo "PASS — approved 16.2.18 exact-artifact verification complete; no rebuild or release package mutation."
