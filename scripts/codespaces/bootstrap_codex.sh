#!/usr/bin/env bash
set -euo pipefail

# Optional local Codex CLI install. It never signs in and never creates an API key.
CODEX_VERSION="${STOCKLAND_CODEX_VERSION:-0.167.0}"
LOCAL_PREFIX="$HOME/.local"
LOCAL_BIN="$LOCAL_PREFIX/bin"
PROFILE_FILE="$HOME/.profile"

export PATH="$LOCAL_BIN:$PATH"

if command -v codex >/dev/null 2>&1; then
  echo "Codex CLI already available: $(codex --version 2>&1 | head -n 1)"
  exit 0
fi

if ! command -v npm >/dev/null 2>&1; then
  echo "Node.js/npm is unavailable. The ERP Codespaces environment remains usable without Codex." >&2
  exit 2
fi

mkdir -p "$LOCAL_BIN"
echo "Installing pinned Codex CLI @openai/codex@$CODEX_VERSION under $LOCAL_PREFIX"
npm install --global --prefix "$LOCAL_PREFIX" --no-audit --no-fund "@openai/codex@$CODEX_VERSION"

if [[ ! -x "$LOCAL_BIN/codex" ]]; then
  echo "Codex CLI executable was not found at $LOCAL_BIN/codex" >&2
  exit 1
fi

if [[ -f "$PROFILE_FILE" ]] && ! grep -Fq 'export PATH="$HOME/.local/bin:$PATH"' "$PROFILE_FILE"; then
  printf '%s\n' 'export PATH="$HOME/.local/bin:$PATH"' >> "$PROFILE_FILE"
elif [[ ! -f "$PROFILE_FILE" ]]; then
  printf '%s\n' 'export PATH="$HOME/.local/bin:$PATH"' > "$PROFILE_FILE"
fi

"$LOCAL_BIN/codex" --version
echo "Installation only. When ready, sign in interactively with: codex login --device-auth"
echo "Use ChatGPT sign-in; do not configure an API key. Free-plan usage is limited."
echo "No authentication file, token, API key, or paid service was created by this script."
