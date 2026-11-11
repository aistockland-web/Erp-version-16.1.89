#!/usr/bin/env python3
"""Validate Stockland project-scoped Codex configuration without external deps."""
from __future__ import annotations

import json
import sys
import tomllib
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]


def main() -> int:
    config_path = ROOT / ".codex/config.toml"
    marketplace_path = ROOT / ".agents/plugins/marketplace.json"
    config = tomllib.loads(config_path.read_text(encoding="utf-8"))
    marketplace = json.loads(marketplace_path.read_text(encoding="utf-8"))

    assert config["agents"]["max_concurrent_threads_per_session"] == 3
    assert config["mcp_servers"]["openaiDeveloperDocs"]["url"] == "https://developers.openai.com/mcp"
    assert config["mcp_servers"]["context7"]["url"] == "https://mcp.context7.com/mcp"
    assert config["plugins"]["superpowers@stockland-codex"]["enabled"] is True

    assert marketplace["name"] == "stockland-codex"
    plugins = {item["name"]: item for item in marketplace["plugins"]}
    superpowers = plugins["superpowers"]
    source = superpowers["source"]
    assert source["source"] == "url"
    assert source["url"] == "https://github.com/obra/superpowers"
    assert source["sha"] == "22f0b463e7a5e579904dd6211a520321035526f7"
    assert superpowers["policy"]["installation"] == "AVAILABLE"

    required_agents = {
        "stockland-explorer.toml": ("stockland_explorer", "read-only"),
        "stockland-implementer.toml": ("stockland_implementer", "workspace-write"),
        "stockland-reviewer.toml": ("stockland_reviewer", "read-only"),
        "stockland-qa.toml": ("stockland_qa", "workspace-write"),
    }
    for filename, (expected_name, expected_sandbox) in required_agents.items():
        path = ROOT / ".codex/agents" / filename
        agent = tomllib.loads(path.read_text(encoding="utf-8"))
        assert agent["name"] == expected_name, filename
        assert agent["sandbox_mode"] == expected_sandbox, filename
        assert agent["description"].strip(), filename
        assert agent["developer_instructions"].strip(), filename

    skill_path = ROOT / ".agents/skills/stockland-erp-delivery/SKILL.md"
    skill = skill_path.read_text(encoding="utf-8")
    assert skill.startswith("---\nname: stockland-erp-delivery\n")
    assert "16.2.13" in skill and "16.2.15" in skill and "16.1.95" in skill

    source_bootstrap = ROOT / "scripts/codespaces/bootstrap_project_source.py"
    source_wrapper = ROOT / "scripts/codespaces/bootstrap_project_source.sh"
    source_readme = ROOT / "src/README.md"
    assert source_bootstrap.is_file()
    assert source_wrapper.is_file()
    assert source_readme.is_file()
    source_text = source_readme.read_text(encoding="utf-8")
    assert "src/stockland-erp-app/" in source_text
    assert "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13" in source_text

    bootstrap = ROOT / "scripts/codespaces/bootstrap_codex.sh"
    assert bootstrap.is_file()
    print("PASS: Codex TOML, project agents, MCP endpoints, plugin pin, and Stockland skill")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (AssertionError, KeyError, OSError, json.JSONDecodeError, tomllib.TOMLDecodeError) as exc:
        print(f"FAIL: invalid Stockland Codex configuration: {exc}", file=sys.stderr)
        raise SystemExit(1)
