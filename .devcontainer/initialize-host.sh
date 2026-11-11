#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ROOT_ENV_FILE="$REPO_ROOT/.env"
COMPOSE_ENV_FILE="$REPO_ROOT/.devcontainer/.env"

if [[ -f "$ROOT_ENV_FILE" && -f "$COMPOSE_ENV_FILE" ]] && ! cmp -s "$ROOT_ENV_FILE" "$COMPOSE_ENV_FILE"; then
  echo "Root .env and .devcontainer/.env differ; refusing to guess which credentials are current." >&2
  exit 1
fi

SOURCE_ENV=""
if [[ -f "$ROOT_ENV_FILE" ]]; then
  SOURCE_ENV="$ROOT_ENV_FILE"
elif [[ -f "$COMPOSE_ENV_FILE" ]]; then
  SOURCE_ENV="$COMPOSE_ENV_FILE"
else
  umask 077
  TEMP_FILE="$(mktemp "$REPO_ROOT/.env.codespaces.XXXXXX")"
  trap 'rm -f "$TEMP_FILE"' EXIT
  python3 - <<'PY' > "$TEMP_FILE"
import secrets

print("STOCKLAND_DEV_DB_ROOT_PASSWORD=" + secrets.token_hex(32))
print("STOCKLAND_DEV_DB_PASSWORD=" + secrets.token_hex(32))
print("STOCKLAND_DEV_WP_ADMIN_PASSWORD=" + secrets.token_urlsafe(36))
PY
  chmod 600 "$TEMP_FILE"
  mv "$TEMP_FILE" "$ROOT_ENV_FILE"
  trap - EXIT
  SOURCE_ENV="$ROOT_ENV_FILE"
fi

mkdir -p "$(dirname "$COMPOSE_ENV_FILE")"
if [[ "$SOURCE_ENV" != "$ROOT_ENV_FILE" ]]; then
  cp "$SOURCE_ENV" "$ROOT_ENV_FILE"
fi
if [[ "$SOURCE_ENV" != "$COMPOSE_ENV_FILE" ]]; then
  cp "$SOURCE_ENV" "$COMPOSE_ENV_FILE"
fi
chmod 600 "$ROOT_ENV_FILE" "$COMPOSE_ENV_FILE"

for key in STOCKLAND_DEV_DB_ROOT_PASSWORD STOCKLAND_DEV_DB_PASSWORD STOCKLAND_DEV_WP_ADMIN_PASSWORD; do
  if ! grep -q "^$key=." "$ROOT_ENV_FILE"; then
    echo "Missing $key in the local ignored .env file." >&2
    exit 1
  fi
done

echo "Stockland Codespaces local-only credentials are initialized."
echo "Credentials are stored in ignored local .env files and are never printed."
