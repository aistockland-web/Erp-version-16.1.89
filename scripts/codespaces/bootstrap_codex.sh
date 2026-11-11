#!/usr/bin/env bash
set -euo pipefail

# Optional local Codex CLI for ChatGPT Free allowance. Not required by ERP.
CODEX_VERSION="0.167.1"
LOCAL_PREFIX="$HOME/.local"
LOCAL_BIN="$LOCAL_PREFIX/bin"
PROFILE_FILE="$HOME/.profile"
export PATH="$LOCAL_BIN:$PATH"

if command -v codex >/dev/null 2>&1; then
  echo "Codex CLI already available: $(codex --version 2>&1 | head -n 1)"
  exit 0
fi

command -v npm >/dev/null 2>&1 || {
  echo "Node/npm is not available; use the repository's Node 22 devcontainer feature." >&2
  exit 1
}
mkdir -p "$LOCAL_BIN"
echo "Installing optional @openai/codex@$CODEX_VERSION under $LOCAL_PREFIX"
if ! npm install --global --prefix "$LOCAL_PREFIX" "@openai/codex@$CODEX_VERSION"; then
  echo "Codex CLI installation did not complete; the ERP environment remains usable. Retry manually with: bash scripts/codespaces/bootstrap_codex.sh" >&2
  exit 0
fi
[[ -x "$LOCAL_BIN/codex" ]] || {
  echo "Codex CLI executable was not installed at $LOCAL_BIN/codex" >&2
  exit 1
}
"$LOCAL_BIN/codex" --version

if [[ -f "$PROFILE_FILE" ]]; then
  grep -Fq 'export PATH="$HOME/.local/bin:$PATH"' "$PROFILE_FILE" || printf '%s\n' 'export PATH="$HOME/.local/bin:$PATH"' >> "$PROFILE_FILE"
else
  printf '%s\n' 'export PATH="$HOME/.local/bin:$PATH"' > "$PROFILE_FILE"
fi
echo "Optional Codex CLI installed."
echo "When ready, run: codex login --device-auth"
echo "Choose Sign in with ChatGPT. Do not use an API key or commit auth files."
