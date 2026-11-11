#!/usr/bin/env python3
"""Validate Stockland's optional, free-plan local Codex project configuration."""
from __future__ import annotations

import argparse
import pathlib
import re
import sys
import tomllib

ROOT = pathlib.Path(__file__).resolve().parents[2]
EXPECTED = {
    "stockland-explorer.toml": ("stockland_explorer", "read-only"),
    "stockland-implementer.toml": ("stockland_implementer", "workspace-write"),
    "stockland-reviewer.toml": ("stockland_reviewer", "read-only"),
    "stockland-qa.toml": ("stockland_qa", "workspace-write"),
}


def fail(message: str) -> None:
    raise SystemExit("FAIL Codex configuration: " + message)


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--root", type=pathlib.Path, default=ROOT)
    root = parser.parse_args().root.resolve()
    config_path = root / ".codex" / "config.toml"
    agents_path = root / ".codex" / "agents"
    bootstrap_path = root / "scripts" / "codespaces" / "bootstrap_codex.sh"
    devcontainer_path = root / ".devcontainer" / "devcontainer.json"
    skill_path = root / ".agents" / "skills" / "stockland-erp-delivery" / "SKILL.md"

    try:
        config = tomllib.loads(config_path.read_text(encoding="utf-8"))
    except (OSError, tomllib.TOMLDecodeError) as exc:
        fail(f"project config cannot be parsed: {exc}")
    if config.get("agents", {}).get("max_concurrent_threads_per_session") != 1:
        fail("agent concurrency must be exactly one")
    if config.get("plugins"):
        fail("marketplace/plugin entries are excluded from the Free baseline")
    if config.get("mcp_servers"):
        fail("external MCP servers are excluded from the minimal Free baseline")

    for filename, (expected_name, sandbox) in EXPECTED.items():
        try:
            profile = tomllib.loads((agents_path / filename).read_text(encoding="utf-8"))
        except (OSError, tomllib.TOMLDecodeError) as exc:
            fail(f"{filename} cannot be parsed: {exc}")
        if profile.get("name") != expected_name or profile.get("sandbox_mode") != sandbox:
            fail(f"{filename} has an unexpected agent name or sandbox")
        if not profile.get("developer_instructions"):
            fail(f"{filename} has empty instructions")

    try:
        devcontainer = __import__("json").loads(devcontainer_path.read_text(encoding="utf-8"))
    except (OSError, ValueError) as exc:
        fail(f"Dev Container JSON cannot be parsed: {exc}")
    host = devcontainer.get("hostRequirements", {})
    if host != {"cpus": 4, "memory": "16gb", "storage": "64gb"}:
        fail(f"Dev Container must request the Owner's 4-core/16-GB/64-GB profile, found {host!r}")
    extensions = devcontainer.get("customizations", {}).get("vscode", {}).get("extensions", [])
    for extension in ("GitHub.copilot", "GitHub.copilot-chat"):
        if extension not in extensions:
            fail(f"VS Code extension is missing: {extension}")

    try:
        skill = skill_path.read_text(encoding="utf-8")
    except OSError as exc:
        fail(f"Stockland delivery skill is missing: {exc}")
    if not skill.startswith("---\nname: stockland-erp-delivery\n"):
        fail("Stockland delivery skill frontmatter is invalid")
    for required in ("16.2.13", "16.2.15", "16.1.95", "49 legacy", "POS", "payroll"):
        if required.casefold() not in skill.casefold():
            fail(f"Stockland delivery skill is missing required project scope: {required}")

    try:
        copilot = (root / ".github" / "copilot-instructions.md").read_text(encoding="utf-8")
    except OSError as exc:
        fail(f"GitHub Copilot repository instructions are missing: {exc}")
    if "Stockland Ethiopia" not in copilot or "PLEDGER" not in copilot:
        fail("Copilot instructions do not contain the ERP business scope")

    try:
        script = bootstrap_path.read_text(encoding="utf-8")
    except OSError as exc:
        fail(f"Codex bootstrap script is missing: {exc}")
    if not re.search(r'^CODEX_VERSION="[0-9]+\.[0-9]+\.[0-9]+"$', script, re.MULTILINE):
        fail("Codex CLI package version must be pinned")
    if "@openai/codex@$CODEX_VERSION" not in script:
        fail("bootstrap must install the pinned Codex package")
    if "OPENAI_API_KEY" in script or "api_key" in script.lower():
        fail("API-key authentication is forbidden")
    if "codex login --device-auth" not in script:
        fail("ChatGPT device authentication instruction is missing")

    print("PASS project config and four scoped agent profiles; one thread; no plugins, MCP or API keys.")
    print("PASS optional CLI installer is pinned and uses ChatGPT device authentication.")
    print("PASS 4-core/16-GB Codespaces profile, Copilot Free extensions, Stockland delivery skill and Copilot instructions.")
    return 0


if __name__ == "__main__":
    sys.exit(main())
