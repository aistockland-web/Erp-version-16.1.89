#!/usr/bin/env python3
"""Regression gate for exact Stockland 16.1.95 HR replacement source inventory."""
import csv, json, sys
from pathlib import Path
root = Path(sys.argv[1] if len(sys.argv) > 1 else "build/hr-replacement-baseline")
report = json.loads((root / "hr_replacement_baseline_inventory.json").read_text(encoding="utf-8"))
with (root / "hr_replacement_49_table_inventory.csv").open(encoding="utf-8-sig", newline="") as handle:
    rows = list(csv.DictReader(handle))
def require(value, message):
    if not value:
        raise SystemExit("FAIL: " + message)
require(report["baseline"]["identity_pass"], "baseline hash/size is not the locked 16.1.95 artifact")
require(report["baseline"]["files"] == 75, "baseline file count is not 75")
require(report["schema"]["total"] == 49 and report["schema"]["core_operations"] == 29 and report["schema"]["accounting_procurement"] == 16 and report["schema"]["legacy_hr_payroll"] == 4, "49-table contract differs")
require(report["schema"]["table_keys_unique"] and report["schema"]["every_registered_key_has_source_ddl"], "table key/DDL mapping incomplete")
require(report["schema"]["explicit_fk_constraint_count"] == 0, "source FK count differs from pinned baseline; review before accepting")
require(len(rows) == 49 and len({row["table_key"] for row in rows}) == 49, "inventory is not 49 unique rows")
legacy = {row["table_key"] for row in rows if row["legacy_hr"].lower() == "true"}
require(legacy == {"employees", "employee_compensation", "payroll_periods", "payroll_lines"}, "legacy HR transition source set differs")
require(report["rest_source_inventory"]["route_registration_calls"] == 116, "REST source inventory differs")
deps = {item["legacy_table"]: item["consumer_file_count"] for item in report["legacy_hr_dependencies"]}
require(deps == {"employee_compensation": 5, "employees": 8, "payroll_lines": 4, "payroll_periods": 4}, "legacy HR lexical consumer map differs")
require("DESIGN/AUDIT ONLY" in report["release_decision"], "audit must not claim product implementation or live acceptance")
print("PASS HR replacement baseline audit: exact ZIP identity, 49-table contract, 116 REST calls, legacy dependencies, non-mutating scope")
