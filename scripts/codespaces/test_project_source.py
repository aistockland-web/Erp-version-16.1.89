#!/usr/bin/env python3
"""Regression tests for the exact, non-destructive Stockland source importer."""
from __future__ import annotations
import json, tempfile, unittest
from pathlib import Path
from bootstrap_project_source import materialize, sha256_file


class SourceBootstrapTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.stage = self.root / "stage"
        self.app = self.stage / "16_2_13" / "stockland-erp-app"
        self.app.mkdir(parents=True)
        self.dest = self.root / "src" / "stockland-erp-app"
        self.marker = self.root / "src" / ".stockland-erp-app.source.json"
        self.report = self.root / "intake.json"
        entry = "<?php\n/**\n * Plugin Name: Stockland Ethiopia ERP\n * Version: 16.2.13\n */\ndefine( 'STOCKLAND_VERSION', '16.2.13' );\n"
        (self.app / "stockland-inventory.php").write_text(entry, encoding="utf-8")
        includes = self.app / "includes"
        includes.mkdir()
        for i in range(79):
            (includes / f"fixture-{i:02d}.php").write_text(f"<?php\n// fixture {i}\n", encoding="utf-8")
        (self.app / "app.js").write_text("/* fixture */\n", encoding="utf-8")
        files = {
            p.relative_to(self.app).as_posix(): sha256_file(p)
            for p in self.app.rglob("*") if p.is_file()
        }
        payload = {"16.2.13": {
            "filename": "STOCKLAND_ERP_16.2.13_WORDPRESS_INSTALLER (1).zip",
            "sha256": "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13",
            "size_bytes": 2188684, "php_files": 80, "file_count": len(files),
            "plugin_root": "stockland-erp-app/", "zip_crc": "PASS",
            "duplicate_paths": 0, "unsafe_paths": 0, "files": files,
        }}
        self.report.write_text(json.dumps(payload), encoding="utf-8")

    def tearDown(self):
        self.tmp.cleanup()

    def test_imports_exact_manifest_and_writes_provenance(self):
        self.assertTrue(materialize(self.stage, self.report, self.dest, self.marker))
        self.assertTrue((self.dest / "stockland-inventory.php").is_file())
        mark = json.loads(self.marker.read_text(encoding="utf-8"))
        self.assertEqual(mark["source_version"], "16.2.13")
        self.assertEqual(mark["archive_sha256"], "74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13")

    def test_rebuild_preserves_edits(self):
        materialize(self.stage, self.report, self.dest, self.marker)
        file = self.dest / "includes" / "fixture-00.php"
        file.write_text("<?php\n// engineer change\n", encoding="utf-8")
        self.assertFalse(materialize(self.stage, self.report, self.dest, self.marker))
        self.assertIn("engineer change", file.read_text(encoding="utf-8"))

    def test_refuses_unmarked_preexisting_folder(self):
        self.dest.mkdir(parents=True)
        keep = self.dest / "sample.txt"
        keep.write_text("preserve", encoding="utf-8")
        with self.assertRaisesRegex(RuntimeError, "Refusing to overwrite"):
            materialize(self.stage, self.report, self.dest, self.marker)
        self.assertEqual(keep.read_text(encoding="utf-8"), "preserve")

    def test_detects_tampered_staging_file(self):
        (self.app / "includes" / "fixture-00.php").write_text("<?php\n// altered\n", encoding="utf-8")
        with self.assertRaisesRegex(RuntimeError, "SHA-256 mismatch"):
            materialize(self.stage, self.report, self.dest, self.marker)
        self.assertFalse(self.dest.exists())

    def test_drift_is_reported_and_strict_mode_fails(self):
        import contextlib, io, os
        materialize(self.stage, self.report, self.dest, self.marker)
        (self.dest / "includes" / "fixture-00.php").write_text("<?php\n// drifted\n", encoding="utf-8")
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            self.assertFalse(materialize(self.stage, self.report, self.dest, self.marker))
        self.assertIn("WARNING: editable source differs", out.getvalue())
        os.environ["STOCKLAND_SOURCE_STRICT"] = "1"
        try:
            with self.assertRaisesRegex(RuntimeError, "drifted"):
                materialize(self.stage, self.report, self.dest, self.marker)
        finally:
            del os.environ["STOCKLAND_SOURCE_STRICT"]

    def test_marker_is_not_left_behind_when_the_move_fails(self):
        from unittest import mock
        with mock.patch("os.rename", side_effect=OSError("simulated crash")):
            with self.assertRaises(OSError):
                materialize(self.stage, self.report, self.dest, self.marker)
        self.assertFalse(self.marker.exists())
        self.assertFalse(self.dest.exists())


if __name__ == "__main__":
    unittest.main(verbosity=2)
