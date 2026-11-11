#!/usr/bin/env python3
"""Synthetic regression tests for the narrowly scoped .15 PHP opening-tag repair."""
from __future__ import annotations

import tempfile
import unittest
import zipfile
from pathlib import Path

from repair_candidate_php_entrypoint import repair_archive, digest_file

TARGET = "stockland-erp-app/includes/class-stockland-schema-contract.php"


class CandidatePhpEntrypointRepairTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp = tempfile.TemporaryDirectory()
        self.root = Path(self.temp.name)
        self.source = self.root / "source.zip"
        self.output = self.root / "repaired.zip"

    def tearDown(self) -> None:
        self.temp.cleanup()

    def make_zip(self, target: bytes) -> None:
        with zipfile.ZipFile(self.source, "w", compression=zipfile.ZIP_DEFLATED) as archive:
            archive.writestr("stockland-erp-app/stockland-inventory.php", b"<?php\n/* Version: 16.2.15 */\n")
            archive.writestr(TARGET, target)
            archive.writestr("stockland-erp-app/includes/unchanged.php", b"<?php\nclass Unchanged {}\n")
            archive.writestr("stockland-erp-app/assets/app.js", b"window.stockland = true;\n")

    def test_only_the_known_member_is_changed_and_zip_is_valid(self) -> None:
        original_target = b"/**\n * Read-only schema inventory.\n */\nfinal class StocklandSchemaContract {}\n"
        self.make_zip(original_target)
        original_sha = digest_file(self.source)
        original_size = self.source.stat().st_size
        receipt = repair_archive(self.source, self.output, original_sha, original_size)
        self.assertEqual(digest_file(self.source), original_sha)
        self.assertEqual(receipt["changed_members"], [TARGET])
        self.assertFalse(receipt["source_archive_mutated"])
        with zipfile.ZipFile(self.source) as source, zipfile.ZipFile(self.output) as output:
            self.assertIsNone(output.testzip())
            self.assertEqual(source.namelist(), output.namelist())
            self.assertEqual(output.read(TARGET), b"<?php\n" + original_target)
            for name in source.namelist():
                if name != TARGET:
                    self.assertEqual(source.read(name), output.read(name))

    def test_wrong_original_hash_is_rejected(self) -> None:
        self.make_zip(b"/**\nclass Broken {}\n")
        with self.assertRaisesRegex(ValueError, "identity mismatch"):
            repair_archive(self.source, self.output, "0" * 64, self.source.stat().st_size)
        self.assertFalse(self.output.exists())

    def test_already_fixed_target_is_not_modified_twice(self) -> None:
        self.make_zip(b"<?php\n/**\nclass AlreadyFixed {}\n")
        with self.assertRaisesRegex(ValueError, "already contains a PHP opening tag"):
            repair_archive(self.source, self.output, digest_file(self.source), self.source.stat().st_size)
        self.assertFalse(self.output.exists())

    def test_unknown_target_signature_is_rejected(self) -> None:
        self.make_zip(b"// not the reviewed source signature\nclass Unexpected {}\n")
        with self.assertRaisesRegex(ValueError, "audited PHP doc-comment signature"):
            repair_archive(self.source, self.output, digest_file(self.source), self.source.stat().st_size)
        self.assertFalse(self.output.exists())

    def test_missing_target_is_rejected(self) -> None:
        with zipfile.ZipFile(self.source, "w") as archive:
            archive.writestr("stockland-erp-app/stockland-inventory.php", b"<?php\n")
        with self.assertRaisesRegex(ValueError, "exactly one repair target"):
            repair_archive(self.source, self.output, digest_file(self.source), self.source.stat().st_size)
        self.assertFalse(self.output.exists())


if __name__ == "__main__":
    unittest.main()
