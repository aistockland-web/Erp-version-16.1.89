#!/usr/bin/env python3
"""Regression tests for the Codespaces master-installer target selector."""
from __future__ import annotations

import os
import shutil
import subprocess
import tempfile
import unittest
from pathlib import Path


REPOSITORY_ROOT = Path(__file__).resolve().parents[2]
SOURCE_SCRIPT = REPOSITORY_ROOT / ".devcontainer" / "verify-master-installer.sh"
EXPECTED_ZIP = "STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip"


class MasterInstallerTargetTests(unittest.TestCase):
    def create_fake_repo(self, root: Path) -> tuple[Path, dict[str, str]]:
        devcontainer = root / ".devcontainer"
        release = root / "scripts" / "release"
        e2e = release / "e2e"
        devcontainer.mkdir(parents=True)
        e2e.mkdir(parents=True)

        wrapper = devcontainer / "verify-master-installer.sh"
        shutil.copy2(SOURCE_SCRIPT, wrapper)

        builder = release / "build_master_installer.py"
        builder.write_text(
            "from pathlib import Path\n"
            f"Path({EXPECTED_ZIP!r}).write_bytes(b'temporary test ZIP')\n"
            "Path('build.marker').write_text('built')\n",
            encoding="utf-8",
        )

        verifier = release / "verify_master_installer.sh"
        verifier.write_text(
            "#!/usr/bin/env bash\n"
            "set -euo pipefail\n"
            'printf "%s|%s\\n" "$1" "$2" >> "$STOCKLAND_TEST_VERIFY_LOG"\n',
            encoding="utf-8",
        )

        runner = e2e / "run_all.sh"
        runner.write_text(
            "#!/usr/bin/env bash\n"
            "set -euo pipefail\n"
            'printf "%s\\n" "$1" >> "$STOCKLAND_TEST_E2E_LOG"\n',
            encoding="utf-8",
        )

        env = os.environ.copy()
        env["STOCKLAND_TEST_VERIFY_LOG"] = str(root / "verify.log")
        env["STOCKLAND_TEST_E2E_LOG"] = str(root / "e2e.log")
        return wrapper, env

    def run_wrapper(self, root: Path, wrapper: Path, env: dict[str, str], *args: str):
        return subprocess.run(
            ["bash", str(wrapper), *args],
            cwd=root,
            env=env,
            text=True,
            capture_output=True,
            check=False,
        )

    def read_lines(self, path: Path) -> list[str]:
        return path.read_text(encoding="utf-8").splitlines() if path.exists() else []

    def assert_target_called(self, root: Path, env: dict[str, str], expected: list[str]) -> None:
        verify_rows = self.read_lines(Path(env["STOCKLAND_TEST_VERIFY_LOG"]))
        e2e_rows = self.read_lines(Path(env["STOCKLAND_TEST_E2E_LOG"]))
        expected_verify = [f"{site}|{root / EXPECTED_ZIP}" for site in expected]
        self.assertEqual(verify_rows, expected_verify)
        self.assertEqual(e2e_rows, expected)

    def test_rejects_unknown_target_before_build_or_database_calls(self) -> None:
        with tempfile.TemporaryDirectory(prefix="stockland-master-target-") as td:
            root = Path(td)
            wrapper, env = self.create_fake_repo(root)
            result = self.run_wrapper(root, wrapper, env, "invalid")
            self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
            self.assertIn("Unsupported verification target: invalid", result.stderr)
            self.assertFalse((root / "build.marker").exists())
            self.assertEqual(self.read_lines(Path(env["STOCKLAND_TEST_VERIFY_LOG"])), [])
            self.assertEqual(self.read_lines(Path(env["STOCKLAND_TEST_E2E_LOG"])), [])

    def test_rejects_extra_arguments_before_build_or_database_calls(self) -> None:
        with tempfile.TemporaryDirectory(prefix="stockland-master-target-") as td:
            root = Path(td)
            wrapper, env = self.create_fake_repo(root)
            result = self.run_wrapper(root, wrapper, env, "mariadb", "mysql8")
            self.assertEqual(result.returncode, 2, result.stdout + result.stderr)
            self.assertIn("Usage:", result.stderr)
            self.assertFalse((root / "build.marker").exists())
            self.assertEqual(self.read_lines(Path(env["STOCKLAND_TEST_VERIFY_LOG"])), [])
            self.assertEqual(self.read_lines(Path(env["STOCKLAND_TEST_E2E_LOG"])), [])

    def test_mariadb_runs_only_the_baseline_site(self) -> None:
        with tempfile.TemporaryDirectory(prefix="stockland-master-target-") as td:
            root = Path(td)
            wrapper, env = self.create_fake_repo(root)
            result = self.run_wrapper(root, wrapper, env, "mariadb")
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn("target: mariadb", result.stdout)
            self.assert_target_called(root, env, ["/var/www/html/site-baseline"])

    def test_mysql8_runs_only_the_mysql8_site(self) -> None:
        with tempfile.TemporaryDirectory(prefix="stockland-master-target-") as td:
            root = Path(td)
            wrapper, env = self.create_fake_repo(root)
            result = self.run_wrapper(root, wrapper, env, "mysql8")
            self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
            self.assertIn("target: mysql8", result.stdout)
            self.assert_target_called(root, env, ["/var/www/html/site-mysql8"])

    def test_all_runs_both_sites_and_default_is_all(self) -> None:
        for args in (("all",), ()):
            with self.subTest(args=args), tempfile.TemporaryDirectory(prefix="stockland-master-target-") as td:
                root = Path(td)
                wrapper, env = self.create_fake_repo(root)
                result = self.run_wrapper(root, wrapper, env, *args)
                self.assertEqual(result.returncode, 0, result.stdout + result.stderr)
                self.assertIn("target: all", result.stdout)
                self.assert_target_called(
                    root,
                    env,
                    ["/var/www/html/site-baseline", "/var/www/html/site-mysql8"],
                )


if __name__ == "__main__":
    unittest.main()
