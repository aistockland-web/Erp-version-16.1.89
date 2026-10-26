#!/usr/bin/env python3
"""Safety/coverage tests for the read-only WP-CLI clone data-health audit."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[2]
AUDIT = ROOT / "ci" / "diagnostics" / "hr_replacement_clone_audit.php"

REQUIRED_CHECKS = {
    "duplicate_employee_codes",
    "blank_employee_codes",
    "duplicate_compensation_effective_dates",
    "orphan_compensation_employee_refs",
    "negative_compensation_amounts",
    "active_employees_missing_effective_compensation",
    "compensation_missing_statutory_fields",
    "orphan_payroll_line_employee_refs",
    "orphan_payroll_line_period_refs",
    "duplicate_period_employee_lines",
    "invalid_payroll_period_ranges",
    "overlapping_payroll_period_pairs",
    "approved_or_paid_periods_missing_journal",
    "period_journal_refs_missing",
    "referenced_payroll_journals_unbalanced",
    "period_header_line_totals_mismatch",
}

FORBIDDEN_SQL = re.compile(
    r"\b(?:INSERT\s+INTO|UPDATE\s+\S+\s+SET|DELETE\s+FROM|REPLACE\s+INTO|"
    r"CREATE\s+TABLE|ALTER\s+TABLE|DROP\s+TABLE|TRUNCATE\s+TABLE|"
    r"CREATE\s+DATABASE|DROP\s+DATABASE|CALL\s+[A-Za-z_])\b",
    re.IGNORECASE,
)

def main() -> None:
    source = AUDIT.read_text(encoding="utf-8")
    assert "defined( 'WP_CLI' )" in source, "must be WP-CLI only, not an HTTP endpoint"
    assert "NO_GO_UNTIL_REVIEWED" in source, "must fail safe on cutover decision"
    assert "no_mutations_performed" in source and "row_level_data_emitted" in source
    assert "NOT_VERIFIED_BY_THIS_SCRIPT" in source, "must not claim installed-source parity"
    assert "494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625" in source
    keys_body = re.search(r"private\s+static\s+function\s+keys\(\)\s*\{\s*return\s+array\((.*?)\);", source, re.S)
    assert keys_body, "expected registered-table key contract is missing"
    keys = re.findall(r"'([a-z][a-z0-9_]*)'", keys_body.group(1))
    assert len(keys) == 49 and len(set(keys)) == 49, f"expected 49 unique table keys; got {len(keys)}"
    assert {"employees", "employee_compensation", "payroll_periods", "payroll_lines"}.issubset(keys)
    for check in REQUIRED_CHECKS:
        assert re.search(r"['\"]" + re.escape(check) + r"['\"]\s*(?:=>|\])", source), f"missing data-health check {check}"
    # The SQL in this file is for SELECT / INFORMATION_SCHEMA metadata and aggregate checks only.
    # Keep this broad lexical guard as a review tripwire, not a proof of SQL semantics.
    assert not FORBIDDEN_SQL.search(source), "potentially mutating SQL statement found in read-only audit tool"
    assert "register_rest_route" not in source and "admin_post_" not in source, "audit tool must not expose a web route"
    assert "full_name" not in source and "bank_account" not in source and "tax_id" not in source, "do not emit sensitive fields"
    assert "SELECT VERSION()" in source and "INFORMATION_SCHEMA.COLUMNS" in source
    assert "INFORMATION_SCHEMA.STATISTICS" in source and "INFORMATION_SCHEMA.KEY_COLUMN_USAGE" in source
    print("PASS HR clone audit static safety/coverage: WP-CLI-only, 49-key contract, 16 aggregate checks, no write SQL or HTTP route")

if __name__ == "__main__":
    main()
