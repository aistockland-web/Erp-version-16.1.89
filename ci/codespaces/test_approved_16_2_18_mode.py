#!/usr/bin/env python3
"""Contract test: a fresh Codespace uses only the approved Stockland ERP 16.2.18 package."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEVCONTAINER = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text(encoding="utf-8"))
POST_CREATE = (ROOT / ".devcontainer/post-create.sh").read_text(encoding="utf-8")
README = (ROOT / ".devcontainer/README.md").read_text(encoding="utf-8")

EXPECTED_MODE = "approved-16.2.18"
GUARD = 'if [[ "${STOCKLAND_DEV_RELEASE_MODE:-}" == "approved-16.2.18" ]]; then'
LEGACY_START = 'echo "=== Create or reuse the exact repaired 16.2.15 QA candidate ==="'

assert DEVCONTAINER.get("containerEnv", {}).get("STOCKLAND_DEV_RELEASE_MODE") == EXPECTED_MODE, (
    "Codespaces must default to the Owner-approved 16.2.18 release mode"
)
assert GUARD in POST_CREATE, "post-create must contain the explicit approved-release guard"
guard_start = POST_CREATE.index(GUARD)
legacy_start = POST_CREATE.index(LEGACY_START)
guard_region = POST_CREATE[guard_start:legacy_start]
assert "Skipping legacy 16.2.13/.15 package intake" in guard_region
assert "verify_approved_16_2_18.sh all" in guard_region
assert "exit 0" in guard_region, "approved mode must exit before historical candidate preparation"
assert guard_start < legacy_start, "approved mode guard must precede all legacy candidate preparation"
assert "Current Codespaces default — approved 16.2.18 only" in README
print("PASS Codespaces defaults to exact approved 16.2.18 and skips 16.2.13/.15 intake.")
