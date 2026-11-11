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

    def test_devcontainer_uses_free_machine_minimum(self) -> None:
        config = json.loads((ROOT / ".devcontainer/devcontainer.json").read_text())
        self.assertEqual(config.get("hostRequirements"), {
            "cpus": 2, "memory": "8gb", "storage": "32gb"
        })

    def test_no_marketplace_or_external_server_configuration(self) -> None:
        config = tomllib.loads((ROOT / ".codex/config.toml").read_text())
        self.assertNotIn("plugins", config)
        self.assertNotIn("mcp_servers", config)
        self.assertEqual(config["agents"]["max_concurrent_threads_per_session"], 1)


if __name__ == "__main__":
    unittest.main()
