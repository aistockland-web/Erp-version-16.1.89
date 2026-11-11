#!/usr/bin/env python3
"""Validate Stockland Free-plan Codex configuration with Python standard library only."""
from __future__ import annotations
import json
import re
import sys
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

def main() -> int:
    config_path_text = (ROOT / ".codex/config.toml").read_text(encoding="utf-8")
    config = tomllib.loads(config_path_text)
    assert config["agents"]["max_concurrent_threads_per_session"] == 1
    assert "plugins" not in config and "mcp_servers" not in config
    required = {
        "stockland-explorer.toml": ("stockland_explorer", "read-only"),
        "stockland-implementer.toml": ("stockland_implementer", "workspace-write"),
        "stockland-reviewer.toml": ("stockland_reviewer", "read-only"),
        "stockland-qa.toml": ("stockland_qa", "workspace-write"),
    }
    combined = config_path_text
    for filename, (name, sandbox) in required.items():
        path = ROOT / ".codex/agents" / filename
        raw = path.read_text(encoding="utf-8")
        agent = tomllib.loads(raw)
        assert agent["name"] == name, filename
        assert agent["sandbox_mode"] == sandbox, filename
        assert agent["model_reasoning_effort"] == "medium", filename
        assert agent["developer_instructions"].strip(), filename
        assert "AGENTS.md" in raw, filename
        combined += raw
    skill = (ROOT / ".agents/skills/stockland-erp-delivery/SKILL.md").read_text(encoding="utf-8")
    assert "16.2.13" in skill and "16.2.15" in skill and "Target 6" in skill
    assert "37 HR lifecycle" in skill and "Codex Cloud" in skill
    script = ROOT / "scripts/codespaces/bootstrap_codex.sh"
    assert script.is_file() and "codex login" in script.read_text(encoding="utf-8")
    assert not (ROOT / ".agents/plugins/marketplace.json").exists(), "Third-party plugin marketplace must not be installed"
    all_text = combined + skill
    assert not re.search(r"sk-[A-Za-z0-9]{20,}", all_text), "Possible API key found"
    assert "OPENAI_API_KEY" not in all_text, "API-key dependency is forbidden"
    dev = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text(encoding="utf-8"))
    host = dev.get("hostRequirements", {})
    assert host.get("cpus") == 4 and host.get("memory") == "16gb"
    agents = (ROOT / "AGENTS.md").read_text(encoding="utf-8")
    assert "15 GB-month" in agents and "no paid overage" in agents
    assert "Target 6" in agents and "Do NOT run" in agents
    prompt = (ROOT / "docs/AGENT_FIRST_PROMPT.md").read_text(encoding="utf-8")
    assert "CODEX_FREE_PLAN_AND_ONE_CODESPACE_2026-11-11.md" in prompt
    assert "Target 6" in prompt
    print("PASS: four project agents, one-thread local Codex config, no paid plugins/API keys, Free-plan machine requirement, and Target 6 handoff")
    return 0

if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (AssertionError, KeyError, OSError, json.JSONDecodeError, tomllib.TOMLDecodeError) as exc:
        print(f"FAIL: invalid Stockland Free-plan Codex setup: {exc}", file=sys.stderr)
        raise SystemExit(1)
