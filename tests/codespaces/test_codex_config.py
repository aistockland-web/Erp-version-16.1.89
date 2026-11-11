from __future__ import annotations

import json
import pathlib
import subprocess
import sys
import tomllib
import unittest

ROOT = pathlib.Path(__file__).resolve().parents[2]


class CodexConfigurationTests(unittest.TestCase):
    def test_project_configuration_and_profiles(self) -> None:
        result = subprocess.run(
            [sys.executable, str(ROOT / "scripts/codespaces/validate_codex_config.py")],
            cwd=ROOT, capture_output=True, text=True, check=False,
        )
        self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
        self.assertIn("one thread", result.stdout)

    def test_devcontainer_uses_requested_16gb_machine_profile(self) -> None:
        config = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text())
        self.assertEqual(config.get("hostRequirements"), {
            "cpus": 4, "memory": "16gb", "storage": "64gb"
        })

    def test_stockland_delivery_skill_is_present(self) -> None:
        skill = (ROOT / ".agents/skills/stockland-erp-delivery/SKILL.md").read_text()
        self.assertTrue(skill.startswith("---\\nname: stockland-erp-delivery\\n"))
        self.assertIn("one complete, production-grade Stockland WordPress ERP/PWA", skill)
        self.assertIn("49 legacy", skill)

    def test_copilot_free_extensions_are_configured(self) -> None:
        config = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text())
        extensions = config["customizations"]["vscode"]["extensions"]
        self.assertIn("GitHub.copilot", extensions)
        self.assertIn("GitHub.copilot-chat", extensions)

    def test_no_marketplace_or_external_server_configuration(self) -> None:
        config = tomllib.loads((ROOT / ".codex/config.toml").read_text())
        self.assertNotIn("plugins", config)
        self.assertNotIn("mcp_servers", config)
        self.assertEqual(config["agents"]["max_concurrent_threads_per_session"], 1)


if __name__ == "__main__":
    unittest.main()
