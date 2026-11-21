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

    # MySQL 8 INFORMATION_SCHEMA results may carry uppercase field labels,
    # while MariaDB may return lowercase labels. Both must compare equally.
    mysql_metadata = table(
        columns=[
            {"COLUMN_NAME": "id", "COLUMN_TYPE": "bigint", "IS_NULLABLE": "NO", "COLUMN_DEFAULT": None, "EXTRA": "auto_increment", "CHARACTER_SET_NAME": None, "COLLATION_NAME": None},
            {"COLUMN_NAME": "status", "COLUMN_TYPE": "varchar(20)", "IS_NULLABLE": "NO", "COLUMN_DEFAULT": "DRAFT", "EXTRA": "", "CHARACTER_SET_NAME": "utf8mb4", "COLLATION_NAME": "utf8mb4_unicode_ci"},
        ],
        indexes=[
            {"INDEX_NAME": "PRIMARY", "NON_UNIQUE": 0, "INDEX_TYPE": "BTREE", "cols": "id::A"},
            {"INDEX_NAME": "status", "NON_UNIQUE": 1, "INDEX_TYPE": "BTREE", "cols": "status::A"},
        ],
        foreign_keys=[
            {"CONSTRAINT_NAME": "fk_status", "COLUMN_NAME": "status", "REFERENCED_TABLE_NAME": "stock_statuses", "REFERENCED_COLUMN_NAME": "code", "ORDINAL_POSITION": 1}
        ],
    )
    expect(
        "MySQL uppercase INFORMATION_SCHEMA labels compare with lowercase MariaDB labels",
        run(table(
            columns=[
                {"column_name": "id", "column_type": "bigint", "is_nullable": "NO", "column_default": None, "extra": "auto_increment", "character_set_name": None, "collation_name": None},
                {"column_name": "status", "column_type": "varchar(20)", "is_nullable": "NO", "column_default": "DRAFT", "extra": "", "character_set_name": "utf8mb4", "collation_name": "utf8mb4_unicode_ci"},
            ],
            indexes=[
                {"index_name": "PRIMARY", "non_unique": 0, "index_type": "BTREE", "cols": "id::A"},
                {"index_name": "status", "non_unique": 1, "index_type": "BTREE", "cols": "status::A"},
            ],
            foreign_keys=[
                {"constraint_name": "fk_status", "column_name": "status", "referenced_table_name": "stock_statuses", "referenced_column_name": "code", "ordinal_position": 1}
            ],
        ), mysql_metadata),
        True,
    )
    print("RESULT PASS (7 cases)")


if __name__ == "__main__":
    main()
