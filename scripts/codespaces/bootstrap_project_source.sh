#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
ARTIFACT_DIR="${STOCKLAND_ARTIFACT_DIR:-$ROOT/artifacts/codespaces}"
STAGE_ROOT="${STOCKLAND_PACKAGE_EXTRACT_ROOT:-/tmp/stockland-candidate-packages}"
ARGS=(
  --stage-root "$STAGE_ROOT"
  --intake-report "$ARTIFACT_DIR/package-intake.json"
  --destination "$ROOT/src/stockland-erp-app"
  --marker "$ROOT/src/.stockland-erp-app.source.json"
)
if [[ "${STOCKLAND_ALLOW_LOCAL_SOURCE_MODIFICATIONS:-0}" == "1" ]]; then
  ARGS+=(--allow-local-modifications)
fi
python3 "$ROOT/scripts/codespaces/bootstrap_project_source.py" "${ARGS[@]}"
