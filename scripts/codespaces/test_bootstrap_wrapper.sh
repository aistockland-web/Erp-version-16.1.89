#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT
mkdir -p "$TMP/bin"
cat > "$TMP/bin/python3" <<'PY'
#!/usr/bin/env bash
printf '%s\n' "$@" > "$STOCKLAND_TEST_ARGS"
PY
chmod +x "$TMP/bin/python3"
export PATH="$TMP/bin:$PATH"
export STOCKLAND_TEST_ARGS="$TMP/args"
export STOCKLAND_ARTIFACT_DIR="$TMP/artifacts"
export STOCKLAND_PACKAGE_EXTRACT_ROOT="$TMP/staged"
env -u STOCKLAND_ALLOW_LOCAL_SOURCE_MODIFICATIONS bash "$ROOT/scripts/codespaces/bootstrap_project_source.sh"
if grep -qx -- '--allow-local-modifications' "$STOCKLAND_TEST_ARGS"; then
  echo "FAIL wrapper enables local modifications by default" >&2; exit 1
fi
grep -qx -- '--stage-root' "$STOCKLAND_TEST_ARGS"
echo "PASS wrapper default is fail-closed (no local-modification override)"
STOCKLAND_ALLOW_LOCAL_SOURCE_MODIFICATIONS=1 bash "$ROOT/scripts/codespaces/bootstrap_project_source.sh"
grep -qx -- '--allow-local-modifications' "$STOCKLAND_TEST_ARGS"
echo "PASS wrapper adds local-modification override only when explicitly requested"
grep -Fq 'bash scripts/codespaces/bootstrap_project_source.sh' "$ROOT/.devcontainer/post-create.sh"
echo "PASS post-create uses the tested source-bootstrap wrapper"
