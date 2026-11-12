#!/usr/bin/env python3
"""Regression tests for exact, crash-safe and non-destructive source import."""
from __future__ import annotations

import hashlib
import json
import tempfile
import unittest
import zipfile
from pathlib import Path
from unittest.mock import patch

import bootstrap_project_source as importer
from bootstrap_project_source import materialize, sha256_file


class SourceBootstrapTests(unittest.TestCase):
    def setUp(self):
        self.tmp = tempfile.TemporaryDirectory()
        self.root = Path(self.tmp.name)
        self.baseline = dict(importer.BASELINE)
        self.baseline.update({
            "sha256": "",
            "size_bytes": 0,
            "php_files": 80,
            "artifact_commit_sha": "b" * 40,
            "archive_git_blob_sha": "c" * 40,
        })
        self.baseline_patch = patch.object(importer, "BASELINE", self.baseline)
        self.baseline_patch.start()
        self.addCleanup(self.baseline_patch.stop)
        self.root_patch = patch.object(importer, "ROOT", self.root)
        self.root_patch.start()
        self.addCleanup(self.root_patch.stop)

        def fake_git(*args):
            if args == ("rev-parse", "HEAD"):
                return "a" * 40
            if args == ("merge-base", "--is-ancestor", "b" * 40, "a" * 40):
                return ""
            if args == ("rev-parse", "b" * 40 + ":" + self.baseline["archive"]):
                return "c" * 40
            if args == ("rev-parse", "a" * 40 + ":" + self.baseline["archive"]):
                return "c" * 40
            raise AssertionError("Unexpected Git provenance command: " + repr(args))

        self.git_patch = patch.object(importer, "git_output", side_effect=fake_git)
        self.git_patch.start()
        self.addCleanup(self.git_patch.stop)
        self.stage = self.root / "stage"
        self.app = self.stage / "16_2_13" / "stockland-erp-app"
        self.app.mkdir(parents=True)
        self.dest = self.root / "src" / "stockland-erp-app"
        self.marker = self.root / "src" / ".stockland-erp-app.source.json"
        self.report = self.root / "intake.json"

        entry = (
            "<?php\n/**\n * Plugin Name: Stockland Ethiopia ERP\n"
            " * Version: 16.2.13\n */\n"
            "define( 'STOCKLAND_VERSION', '16.2.13' );\n"
        )
        (self.app / "stockland-inventory.php").write_text(entry, encoding="utf-8")
        includes = self.app / "includes"
        includes.mkdir()
        for index in range(79):
            (includes / f"fixture-{index:02d}.php").write_text(
                f"<?php\n// fixture {index}\n", encoding="utf-8"
            )
        (self.app / "app.js").write_text("/* fixture */\n", encoding="utf-8")
        self.files = {
            path.relative_to(self.app).as_posix(): sha256_file(path)
            for path in self.app.rglob("*") if path.is_file()
        }
        archive_path = self.root / self.baseline["archive"]
        with zipfile.ZipFile(archive_path, "w", compression=zipfile.ZIP_DEFLATED) as package:
            for path in self.app.rglob("*"):
                if path.is_file():
                    package.write(path, "stockland-erp-app/" + path.relative_to(self.app).as_posix())
        self.baseline["sha256"] = hashlib.sha256(archive_path.read_bytes()).hexdigest()
        self.baseline["size_bytes"] = archive_path.stat().st_size
        sidecar_path = self.root / self.baseline["sidecar"]
        sidecar_path.write_text(
            self.baseline["sha256"] + "  " + self.baseline["sidecar_logical_filename"] + "\n",
            encoding="utf-8",
        )
        payload = {
            "16.2.13": {
                "filename": self.baseline["archive"],
                "sha256": self.baseline["sha256"],
                "size_bytes": self.baseline["size_bytes"],
                "php_files": 80,
                "file_count": len(self.files),
                "plugin_root": "stockland-erp-app/",
                "zip_crc": "PASS",
                "duplicate_paths": 0,
                "unsafe_paths": 0,
                "files": self.files,
                "artifact_provenance": {
                    "repository": self.baseline["repository"],
                    "artifact_commit_sha": self.baseline["artifact_commit_sha"],
                    "archive_git_blob_sha": self.baseline["archive_git_blob_sha"],
                    "verified_head_sha": "a" * 40,
                },
            }
        }
        self.report.write_text(json.dumps(payload), encoding="utf-8")

    def tearDown(self):
        self.tmp.cleanup()

    def import_source(self):
        return materialize(self.stage, self.report, self.dest, self.marker)

    def test_imports_exact_manifest_then_records_provenance(self):
        self.assertTrue(self.import_source())
        self.assertTrue((self.dest / "stockland-inventory.php").is_file())
        mark = json.loads(self.marker.read_text(encoding="utf-8"))
        self.assertEqual(mark["source_version"], "16.2.13")
        self.assertEqual(mark["repository"], self.baseline["repository"])
        self.assertEqual(mark["artifact_commit_sha"], self.baseline["artifact_commit_sha"])
        self.assertEqual(mark["archive_git_blob_sha"], self.baseline["archive_git_blob_sha"])
        self.assertEqual(
            mark["archive_sha256"],
            self.baseline["sha256"],
        )
        self.assertEqual(mark["file_count"], len(self.files))

    def test_unchanged_repeat_import_rehashes_and_preserves_source(self):
        self.import_source()
        self.assertFalse(self.import_source())

    def test_marked_source_drift_fails_closed_without_deleting_local_changes(self):
        self.import_source()
        changed = self.dest / "includes" / "fixture-00.php"
        changed.write_text("<?php\n// engineer change\n", encoding="utf-8")

        with patch.dict("os.environ", {"STOCKLAND_SOURCE_STRICT": "1"}):
            with self.assertRaisesRegex(RuntimeError, "drifted from the exact .13 intake manifest"):
                self.import_source()

        self.assertIn("engineer change", changed.read_text(encoding="utf-8"))
        self.assertTrue(self.marker.is_file())

    def test_explicit_local_modification_override_preserves_tree_without_relabeling_it(self):
        self.import_source()
        changed = self.dest / "includes" / "fixture-00.php"
        changed.write_text("<?php\n// engineer change\n", encoding="utf-8")
        original_marker = self.marker.read_bytes()

        self.assertFalse(
            materialize(
                self.stage,
                self.report,
                self.dest,
                self.marker,
                allow_local_modifications=True,
            )
        )
        self.assertIn("engineer change", changed.read_text(encoding="utf-8"))
        self.assertEqual(self.marker.read_bytes(), original_marker)

    def test_marker_manifest_digest_mismatch_fails_and_preserves_source(self):
        self.import_source()
        mark = json.loads(self.marker.read_text(encoding="utf-8"))
        mark["file_manifest_sha256"] = "0" * 64
        self.marker.write_text(json.dumps(mark), encoding="utf-8")
        keep = self.dest / "includes" / "fixture-01.php"
        before = keep.read_bytes()

        with self.assertRaisesRegex(RuntimeError, "file_manifest_sha256"):
            self.import_source()

        self.assertEqual(keep.read_bytes(), before)

    def test_exact_unmarked_destination_recovers_interrupted_import(self):
        self.dest.parent.mkdir(parents=True)
        import shutil
        shutil.copytree(self.app, self.dest)

        self.assertFalse(self.import_source())
        self.assertTrue(self.marker.is_file())

    def test_unmarked_drift_is_not_adopted_or_overwritten(self):
        self.dest.mkdir(parents=True)
        keep = self.dest / "sample.txt"
        keep.write_text("preserve", encoding="utf-8")

        with self.assertRaisesRegex(RuntimeError, "unmarked pre-existing source"):
            self.import_source()

        self.assertEqual(keep.read_text(encoding="utf-8"), "preserve")

    def test_marked_source_symlink_is_rejected_even_in_non_strict_mode(self):
        self.import_source()
        link = self.dest / "includes" / "unsafe-link.php"
        link.symlink_to(self.dest / "includes" / "fixture-00.php")

        with self.assertRaisesRegex(RuntimeError, "Symlink in source tree"):
            self.import_source()

        self.assertTrue(link.is_symlink())
        self.assertTrue(self.marker.is_file())

    def test_marker_is_not_published_when_source_directory_move_fails(self):
        with patch("bootstrap_project_source.os.rename", side_effect=OSError("simulated move failure")):
            with self.assertRaisesRegex(OSError, "simulated move failure"):
                self.import_source()

        self.assertFalse(self.dest.exists())
        self.assertFalse(self.marker.exists())

    def test_unmarked_exact_tree_recovers_after_marker_publication_failure(self):
        with patch.object(importer, "write_provenance_marker", side_effect=OSError("simulated marker failure")):
            with self.assertRaisesRegex(OSError, "simulated marker failure"):
                self.import_source()

        self.assertTrue(self.dest.is_dir())
        self.assertFalse(self.marker.exists())
        self.assertFalse(self.import_source())
        self.assertTrue(self.marker.is_file())

    def test_matching_stale_marker_without_destination_is_recovered(self):
        report = importer.read_baseline_report(self.report)
        provenance = importer.expected_provenance(report)
        self.marker.parent.mkdir(parents=True, exist_ok=True)
        self.marker.write_text(json.dumps(provenance), encoding="utf-8")

        self.assertTrue(self.import_source())
        self.assertTrue(self.dest.is_dir())
        self.assertTrue(self.marker.is_file())

    def test_report_manifest_is_recomputed_from_the_actual_pinned_archive(self):
        payload = json.loads(self.report.read_text(encoding="utf-8"))
        payload["16.2.13"]["files"]["stockland-inventory.php"] = "0" * 64
        self.report.write_text(json.dumps(payload), encoding="utf-8")
        with self.assertRaisesRegex(RuntimeError, "manifest does not match the actual pinned ZIP"):
            self.import_source()
        self.assertFalse(self.dest.exists())
        self.assertFalse(self.marker.exists())

    def test_wrong_git_artifact_blob_is_rejected(self):
        payload = json.loads(self.report.read_text(encoding="utf-8"))
        payload["16.2.13"]["artifact_provenance"]["archive_git_blob_sha"] = "0" * 40
        self.report.write_text(json.dumps(payload), encoding="utf-8")
        with self.assertRaisesRegex(RuntimeError, "Git artifact provenance mismatch"):
            self.import_source()
        self.assertFalse(self.dest.exists())

    def test_detects_tampered_staging_file_before_copy(self):
        (self.app / "includes" / "fixture-00.php").write_text(
            "<?php\n// altered\n", encoding="utf-8"
        )

        with self.assertRaisesRegex(RuntimeError, "Per-file SHA-256 mismatch"):
            self.import_source()

        self.assertFalse(self.dest.exists())
        self.assertFalse(self.marker.exists())

    def test_drift_is_reported_and_strict_mode_fails(self):
        import contextlib, io, os
        materialize(self.stage, self.report, self.dest, self.marker)
        (self.dest / "includes" / "fixture-00.php").write_text("<?php\n// drifted\n", encoding="utf-8")
        out = io.StringIO()
        with contextlib.redirect_stdout(out):
            self.assertFalse(materialize(self.stage, self.report, self.dest, self.marker))
        self.assertIn("WARNING: editable source differs", out.getvalue())
        with patch.dict(os.environ, {"STOCKLAND_SOURCE_STRICT": "1"}):
            with self.assertRaisesRegex(RuntimeError, "drifted"):
                materialize(self.stage, self.report, self.dest, self.marker)

    def test_marker_is_not_left_behind_when_the_move_fails(self):
        from unittest import mock
        with mock.patch("os.rename", side_effect=OSError("simulated crash")):
            with self.assertRaises(OSError):
                materialize(self.stage, self.report, self.dest, self.marker)
        self.assertFalse(self.marker.exists())
        self.assertFalse(self.dest.exists())


if __name__ == "__main__":
    unittest.main(verbosity=2)
