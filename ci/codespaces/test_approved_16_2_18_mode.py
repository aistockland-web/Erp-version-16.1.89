#!/usr/bin/env python3
"""Contract test: a fresh Codespace uses only the approved Stockland ERP 16.2.18 package."""
from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
DEVCONTAINER = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text(encoding="utf-8"))
POST_CREATE = (ROOT / ".devcontainer/post-create.sh").read_text(encoding="utf-8")
README = (ROOT / ".devcontainer/README.md").read_text(encoding="utf-8")
COMPOSE = (ROOT / ".devcontainer/docker-compose.yml").read_text(encoding="utf-8")

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
assert DEVCONTAINER.get("hostRequirements") == {
    "cpus": 2,
    "memory": "8gb",
    "storage": "32gb",
}, "Free-plan profile must request the smallest supported Codespaces machine"
assert DEVCONTAINER.get("forwardPorts") == [8080, 8082], (
    "Only MariaDB and optional MySQL 8.0.46 private QA ports should be forwarded"
)
workspace_services = COMPOSE.split("  mariadb:", 1)[0]
workspace_dependencies = workspace_services.split("    depends_on:", 1)[1]
assert "wordpress_upgrade:" not in workspace_dependencies, (
    "Legacy upgrade WordPress must not be a default workspace dependency"
)
legacy_upgrade_service = COMPOSE.split("  wordpress_upgrade:", 1)[1].split("  mysql8:", 1)[0]
assert 'profiles: ["legacy-upgrade"]' in legacy_upgrade_service, (
    "Historical 16.2.13/16.2.15 WordPress upgrade site must be explicit opt-in"
)
assert 'profiles: ["mysql8"]' in COMPOSE, "MySQL 8 QA must remain an optional profile"
print("PASS approved 16.2.18-only Codespaces mode, smallest machine, and lean Compose defaults.")
