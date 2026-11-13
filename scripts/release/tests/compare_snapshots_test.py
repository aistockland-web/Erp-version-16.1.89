#!/usr/bin/env python3
"""Focused regressions for upgrade snapshot row preservation and schema allowlists."""
import json
import subprocess
import sys
import tempfile
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / "compare_snapshots.py"


def table(*, rows=1, fingerprints=None, columns=None, indexes=None, foreign_keys=None, complete=True):
    return {
        "rows": rows,
        "columns": columns or [
            {"column_name": "id", "column_type": "bigint", "is_nullable": "NO", "column_default": None, "extra": "auto_increment", "character_set_name": None, "collation_name": None}
        ],
        "indexes": indexes or [{"index_name": "PRIMARY", "non_unique": 0, "index_type": "BTREE", "cols": "id::A"}],
        "foreign_keys": foreign_keys or [],
        "row_fingerprints": fingerprints or {"row1": "hash1"},
        "row_fingerprints_complete": complete,
        "row_fingerprints_limit": 5000,
    }


def run(before_table, after_table, name="stock_customers"):
    before = {"version": "16.2.13", "tables": {name: before_table}}
    after = {"version": "16.2.18", "tables": {name: after_table}}
    with tempfile.TemporaryDirectory() as directory:
        first = Path(directory) / "before.json"
        second = Path(directory) / "after.json"
        first.write_text(json.dumps(before), encoding="utf-8")
        second.write_text(json.dumps(after), encoding="utf-8")
        return subprocess.run(
            [sys.executable, str(SCRIPT), str(first), str(second)],
            check=False,
            capture_output=True,
            text=True,
        )


def expect(label, result, success):
    actual = result.returncode == 0
    if actual != success:
        raise AssertionError(f"{label}: expected success={success}, exit={result.returncode}\n{result.stdout}\n{result.stderr}")
    print("PASS", label)


def main():
    expect(
        "append-only audit table can grow while retaining every old row",
        run(table(rows=1, fingerprints={"old": "same"}), table(rows=2, fingerprints={"old": "same", "new": "new"}), "stock_audit_logs"),
        True,
    )
    expect(
        "append-only audit table cannot rewrite a pre-existing row",
        run(table(rows=1, fingerprints={"old": "before"}), table(rows=2, fingerprints={"old": "after", "new": "new"}), "stock_audit_logs"),
        False,
    )
    before_rate = table(columns=[
        {"column_name": "id", "column_type": "bigint", "is_nullable": "NO", "column_default": None, "extra": "auto_increment", "character_set_name": None, "collation_name": None},
        {"column_name": "income_tax_rate", "column_type": "decimal(5,2)", "is_nullable": "NO", "column_default": "0.00", "extra": "", "character_set_name": None, "collation_name": None},
    ])
    after_rate = table(columns=[
        {"column_name": "id", "column_type": "bigint", "is_nullable": "NO", "column_default": None, "extra": "auto_increment", "character_set_name": None, "collation_name": None},
        {"column_name": "income_tax_rate", "column_type": "decimal(5,2)", "is_nullable": "YES", "column_default": None, "extra": "", "character_set_name": None, "collation_name": None},
    ])
    expect(
        "only the approved payroll neutral-default transition is allowed",
        run(before_rate, after_rate, "stock_payroll_lines"),
        True,
    )
    before_bad = table(columns=[
        {"column_name": "id", "column_type": "bigint", "is_nullable": "NO", "column_default": None, "extra": "auto_increment", "character_set_name": None, "collation_name": None},
        {"column_name": "status", "column_type": "varchar(20)", "is_nullable": "NO", "column_default": "DRAFT", "extra": "", "character_set_name": None, "collation_name": None},
    ])
    after_bad = table(columns=[
        {"column_name": "id", "column_type": "bigint", "is_nullable": "NO", "column_default": None, "extra": "auto_increment", "character_set_name": None, "collation_name": None},
        {"column_name": "status", "column_type": "varchar(20)", "is_nullable": "YES", "column_default": None, "extra": "", "character_set_name": None, "collation_name": None},
    ])
    expect(
        "unapproved column nullability/default changes are rejected",
        run(before_bad, after_bad),
        False,
    )
    index_changed = table(indexes=[
        {"index_name": "PRIMARY", "non_unique": 0, "index_type": "BTREE", "cols": "id::A"},
        {"index_name": "status", "non_unique": 1, "index_type": "BTREE", "cols": "status::A"},
    ])
    expect("unapproved index additions are rejected", run(table(), index_changed), False)
    expect(
        "incomplete row fingerprints cannot produce a preservation pass",
        run(table(complete=False), table(complete=False)),
        False,
    )
    print("RESULT PASS (6 cases)")


if __name__ == "__main__":
    main()
