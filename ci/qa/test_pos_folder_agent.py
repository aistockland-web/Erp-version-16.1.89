#!/usr/bin/env python3
"""Unit tests for branch-scoped POS folder-agent staging behavior."""
from __future__ import annotations

import importlib.util
import sqlite3
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

ROOT = Path(__file__).resolve().parents[2]
AGENT_PATH = ROOT / "tools/pos-folder-agent/stockland_pos_folder_agent.py"
SPEC = importlib.util.spec_from_file_location("stockland_pos_folder_agent", AGENT_PATH)
assert SPEC and SPEC.loader
agent = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(agent)


class FolderAgentStateTests(unittest.TestCase):
    def test_hash_is_scoped_by_branch_and_business_date(self):
        with tempfile.TemporaryDirectory() as tmp:
            db = agent.open_state(Path(tmp) / "state.sqlite3", 3)
            try:
                digest = "a" * 64
                self.assertFalse(agent.was_uploaded(db, 3, "2026-11-07", digest))
                db.execute(
                    """INSERT INTO uploaded_sources_scoped
                       (branch_id,business_date,sha256,original_name,job_id,
                        job_status,uploaded_at,moved_path)
                       VALUES (?,?,?,?,?,?,?,?)""",
                    (3, "2026-11-07", digest, "pos.xlsx", 7, "READY", "now", "done/pos.xlsx"),
                )
                db.commit()
                self.assertTrue(agent.was_uploaded(db, 3, "2026-11-07", digest))
                self.assertFalse(agent.was_uploaded(db, 3, "2026-11-08", digest))
                self.assertFalse(agent.was_uploaded(db, 4, "2026-11-07", digest))
            finally:
                db.close()

    def test_migrates_existing_unscoped_local_history(self):
        with tempfile.TemporaryDirectory() as tmp:
            path = Path(tmp) / "state.sqlite3"
            db = sqlite3.connect(path)
            db.execute(
                """CREATE TABLE uploaded_sources (
                   sha256 TEXT PRIMARY KEY, original_name TEXT NOT NULL,
                   business_date TEXT NOT NULL, job_id INTEGER,
                   job_status TEXT NOT NULL, uploaded_at TEXT NOT NULL,
                   moved_path TEXT)"""
            )
            db.execute(
                "INSERT INTO uploaded_sources VALUES (?,?,?,?,?,?,?)",
                ("b" * 64, "old.xlsx", "2026-11-07", 12, "READY", "now", "done/old.xlsx"),
            )
            db.commit()
            db.close()

            migrated = agent.open_state(path, 5)
            try:
                self.assertTrue(agent.was_uploaded(migrated, 5, "2026-11-07", "b" * 64))
                self.assertFalse(agent.was_uploaded(migrated, 6, "2026-11-07", "b" * 64))
            finally:
                migrated.close()

    def test_stages_only_to_preview_and_moves_successful_source(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            inbox = root / "inbox"
            processed = root / "processed"
            inbox.mkdir()
            processed.mkdir()
            source = inbox / "branch-pos-2026-11-07.csv"
            source.write_text("SKU,Qty,Price\nSTK-001,1,10.00\n", encoding="utf-8")
            config = {
                "branch_id": 3,
                "inbox": inbox,
                "processed": processed,
                "state_db": root / "state.sqlite3",
                "stable_seconds": 0,
                "max_bytes": agent.DEFAULT_MAX_BYTES,
                "fallback_date": None,
            }
            with patch.object(agent, "upload_file", return_value={"job_id": 21, "status": "READY"}) as upload:
                result = agent.process_once(config)
            self.assertEqual(result, 0)
            upload.assert_called_once()
            self.assertFalse(source.exists())
            moved = list(processed.glob("*.csv"))
            self.assertEqual(len(moved), 1)
            db = agent.open_state(config["state_db"], 3)
            try:
                digest = agent.sha256_file(moved[0])
                self.assertTrue(agent.was_uploaded(db, 3, "2026-11-07", digest))
            finally:
                db.close()

    def test_rejected_upload_keeps_source_in_inbox(self):
        with tempfile.TemporaryDirectory() as tmp:
            root = Path(tmp)
            inbox = root / "inbox"
            processed = root / "processed"
            inbox.mkdir()
            processed.mkdir()
            source = inbox / "branch-pos-2026-11-07.csv"
            source.write_text("SKU,Qty,Price\nSTK-001,1,10.00\n", encoding="utf-8")
            config = {
                "branch_id": 3,
                "inbox": inbox,
                "processed": processed,
                "state_db": root / "state.sqlite3",
                "stable_seconds": 0,
                "max_bytes": agent.DEFAULT_MAX_BYTES,
                "fallback_date": None,
            }
            with patch.object(agent, "upload_file", side_effect=agent.AgentError("rejected")):
                result = agent.process_once(config)
            self.assertEqual(result, 1)
            self.assertTrue(source.exists())
            self.assertEqual(list(processed.glob("*.csv")), [])

    def test_invalid_or_undated_filename_is_not_guessed(self):
        with tempfile.TemporaryDirectory() as tmp:
            source = Path(tmp) / "pos-today.csv"
            source.write_text("SKU,Qty\nSTK-001,1\n", encoding="utf-8")
            with self.assertRaises(agent.AgentError):
                agent.business_date_for_file(source)


if __name__ == "__main__":
    unittest.main(verbosity=2)
