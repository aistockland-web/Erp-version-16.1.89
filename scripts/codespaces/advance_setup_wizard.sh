#!/usr/bin/env bash
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
SITE="$1"
LABEL="$2"
if [[ -z "$SITE" || -z "$LABEL" ]]; then
  echo "Usage: advance_setup_wizard.sh SITE_PATH LABEL" >&2
  exit 2
fi
ARTIFACT_DIR="$REPO_ROOT/artifacts/codespaces"
mkdir -p "$ARTIFACT_DIR"
chmod 0770 "$ARTIFACT_DIR"

source "$REPO_ROOT/scripts/codespaces/wp_cli_wrapper.sh"

SAFE_LABEL="$(printf '%s' "$LABEL" | tr -cs 'A-Za-z0-9._-' '_')"
SETUP_RECEIPT="$ARTIFACT_DIR/$SAFE_LABEL-setup.json"
FINGERPRINT="$ARTIFACT_DIR/$SAFE_LABEL-schema.json"

wp_as_www --path="$SITE" eval-file "$REPO_ROOT/scripts/codespaces/advance_setup_wizard.php" > "$SETUP_RECEIPT"
python3 -m json.tool "$SETUP_RECEIPT" >/dev/null
wp_as_www --path="$SITE" eval-file "$REPO_ROOT/scripts/codespaces/schema_fingerprint.php" > "$FINGERPRINT"
python3 -m json.tool "$FINGERPRINT" >/dev/null

python3 - "$FINGERPRINT" <<'PY'
import json, sys
from pathlib import Path
p = Path(sys.argv[1])
d = json.loads(p.read_text(encoding="utf-8"))
missing = d.get("missing_declared_tables", [])
if missing:
    raise SystemExit("Stockland schema fingerprint has missing declared tables: " + ", ".join(missing))
print("PASS physical Stockland table inventory:", d.get("physical_stockland_table_count"),
      "tables; declared schema keys:", d.get("declared_runtime_key_counts"))
print("NOTE schema contract approval remains separate:", d.get("schema_approval"))
PY

echo "PASS authenticated Stockland setup wizard at $SITE; receipts: $SETUP_RECEIPT, $FINGERPRINT"
