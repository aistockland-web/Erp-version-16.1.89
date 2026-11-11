#!/usr/bin/env bash
set -euo pipefail

CODEX_VERSION="0.167.0"
LOCAL_PREFIX="$HOME/.local"
LOCAL_BIN="$LOCAL_PREFIX/bin"
PROFILE_FILE="$HOME/.profile"

export PATH="$LOCAL_BIN:$PATH"

if command -v codex >/dev/null 2>&1; then
  echo "Codex CLI already available: $(codex --version 2>&1 | head -n 1)"
  exit 0
fi

command -v npm >/dev/null 2>&1 || {
  echo "Node/npm is not available; install the Codespaces Node 22 feature first." >&2
  exit 1
}

mkdir -p "$LOCAL_BIN"

echo "Installing pinned Codex CLI @openai/codex@$CODEX_VERSION under $LOCAL_PREFIX"
if ! npm install --global --prefix "$LOCAL_PREFIX" "@openai/codex@$CODEX_VERSION"; then
  echo "Codex CLI installation failed. The ERP devcontainer can still be used; rerun this script after network/package access is restored." >&2
  exit 0
fi

if [[ -x "$LOCAL_BIN/codex" ]]; then
  "$LOCAL_BIN/codex" --version
else
  echo "npm completed but the Codex executable was not found at $LOCAL_BIN/codex" >&2
  exit 1
fi

if [[ -f "$PROFILE_FILE" ]] && ! grep -Fq 'export PATH="$HOME/.local/bin:$PATH"' "$PROFILE_FILE"; then
  printf '%s\n' 'export PATH="$HOME/.local/bin:$PATH"' >> "$PROFILE_FILE"
elif [[ ! -f "$PROFILE_FILE" ]]; then
  printf '%s\n' 'export PATH="$HOME/.local/bin:$PATH"' > "$PROFILE_FILE"
fi

echo "Codex CLI is installed. Sign in interactively with: codex login --device-auth"
echo "No API key or authentication token was created or stored by this script."
