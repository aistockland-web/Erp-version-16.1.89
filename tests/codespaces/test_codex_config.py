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
            "cpus": 4, "memory": "16gb"
        })

    def test_stockland_delivery_skill_is_present(self) -> None:
        skill = (ROOT / ".agents/skills/stockland-erp-delivery/SKILL.md").read_text()
        self.assertTrue(skill.startswith("---\nname: stockland-erp-delivery\n"))
        self.assertIn("one complete, production-grade Stockland WordPress ERP/PWA", skill)
        self.assertIn("49 legacy", skill)

    def test_copilot_free_extensions_are_configured(self) -> None:
        config = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text())
        extensions = config["customizations"]["vscode"]["extensions"]
        self.assertIn("GitHub.copilot", extensions)
        self.assertIn("GitHub.copilot-chat", extensions)

    def test_pinned_optional_superpowers_and_no_external_mcp_or_credentials(self) -> None:
        config = tomllib.loads((ROOT / ".codex/config.toml").read_text())
        self.assertEqual(config["plugins"], {"superpowers@stockland-codex": {"enabled": True}})
        self.assertNotIn("mcp_servers", config)
        self.assertEqual(config["agents"]["max_concurrent_threads_per_session"], 1)
        marketplace = json.loads((ROOT / ".agents/plugins/marketplace.json").read_text())
        self.assertEqual(marketplace["name"], "stockland-codex")
        plugin = next(item for item in marketplace["plugins"] if item["name"] == "superpowers")
        self.assertEqual(plugin["source"]["url"], "https://github.com/obra/superpowers")
        self.assertEqual(plugin["source"]["sha"], "22f0b463e7a5e579904dd6211a520321035526f7")
        self.assertEqual(plugin["policy"], {"installation": "AVAILABLE", "authentication": "ON_INSTALL"})


if __name__ == "__main__":
    unittest.main()
