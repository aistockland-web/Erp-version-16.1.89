#!/usr/bin/env python3
"""Package-level acceptance tests for the 16.1.96 HR/payroll lifecycle candidate.

These checks inspect the exact WordPress installer ZIP without connecting to a
WordPress database or using real employee data. They do not replace staging or
legal acceptance.
"""
import hashlib
import json
import re
import sys
import zipfile
from pathlib import Path

def require(ok, message):
    if not ok:
        raise AssertionError(message)
    print("PASS " + message)

def main():
    if len(sys.argv) != 2:
        raise SystemExit("usage: test_full_hr_payroll_16_1_96.py <installer.zip>")
    package = Path(sys.argv[1])
    require(package.is_file(), "candidate installer exists")
    digest = hashlib.sha256(package.read_bytes()).hexdigest()
    print("Installer SHA-256: " + digest)
    with zipfile.ZipFile(package) as z:
        require(z.testzip() is None, "ZIP CRC and archive integrity")
        names = [i.filename for i in z.infolist() if not i.is_dir()]
        require(len(names) == 77 and len(set(names)) == 77, "77 unique files: 75 baseline + 2 HR controllers")
        require(all(n.startswith("stockland-erp-app/") and ".." not in n.split("/") for n in names), "single plugin root and safe paths")
        root = "stockland-erp-app/"
        files = {n[len(root):]: z.read(n) for n in names}
        require("includes/class-stockland-hr-lifecycle.php" in files, "expanded HR lifecycle schema included")
        require("includes/rest/class-rest-hr-lifecycle.php" in files, "expanded HR lifecycle REST handlers included")
        main = files["stockland-inventory.php"].decode()
        manifest = json.loads(files["RELEASE_MANIFEST.json"].decode())
        modules = json.loads(files["assets/css/modules.json"].decode())
        app = files["assets/app.js"].decode()
        schema = files["includes/class-stockland-hr-lifecycle.php"].decode()
        rest = files["includes/rest/class-rest-hr-lifecycle.php"].decode()
        css = files["assets/app.css"].decode()
        require("Version:           16.1.96" in main and "define( 'STOCKLAND_VERSION', '16.1.96' );" in main, "plugin version/header matches 16.1.96")
        require(manifest.get("release") == "16.1.96" and modules.get("release") == "16.1.96", "release manifest and CSS modules agree")
        contract = manifest.get("schema_contract", {})
        require(contract == {"total": 73, "core_operations": 29, "accounting_procurement": 16, "hr_payroll": 28}, "73-table additive schema contract declared")
        required_table_names = [
            "hr_workforce_plans","hr_positions","hr_requisitions","hr_candidates",
            "hr_applications","hr_interviews","hr_documents","hr_contracts",
            "hr_onboarding_tasks","hr_shifts","hr_attendance","hr_leave_policies",
            "hr_leave_balances","hr_leave_requests","hr_compensation_components",
            "hr_rule_sets","hr_rule_components","hr_employee_events","hr_payroll_events",
            "hr_payroll_payments","hr_payroll_adjustments","hr_exit_settlements",
            "hr_training_records","hr_performance_reviews"
        ]
        for name in required_table_names:
            require(("'" + name + "'") in schema, "logical table key " + name)
        require("CREATE TABLE {$p}hr_workforce_plans" in schema, "DDL uses the single Stockland prefix (no stock_stock table names)")
        require("CREATE TABLE {$p}stock_hr_workforce_plans" not in schema, "no duplicated stock_ prefix in HR DDL")
        require("function payroll_rules_for_period" in schema and "review_status='APPROVED'" in schema, "payroll only accepts approved effective-dated rules")
        require("EXEMPT_CAP" in schema and "Stockland_Money::minor( $amount ), Stockland_Money::minor( $rule['exemption_cap'] )" in schema, "allowance exemption uses the approved category cap")
        require("Stockland_Money::subtract( $amount, $exempt )" in schema, "taxable remainder is derived from original allowance minus exemption")
        require("Stockland_Money::add( $taxable_total" in schema and "result['exempt_total']" in schema, "taxable/exempt aggregate calculation is present")
        require("exemption_cap" in schema and not re.search(r"exemption_cap.{0,24}(?:1000(?:\.00)?|'1000(?:\.00)?')", schema, re.I), "no ETB 1,000 exemption cap is hard-coded")
        require("allowance-preview" in rest and "allowance_rule_version" in rest, "allowance preview records rule provenance")
        require("rules/(?P<id>\\d+)/approve" in rest and "Maker-checker" in rest, "rules have separate approval and maker-checker guard")
        require("applications/(?P<id>\\d+)/select" in rest, "recruitment selection endpoint is present")
        require("contracts/(?P<id>\\d+)/sign" in rest and "employees/(?P<id>\\d+)/activate" in rest, "signed-contract and controlled hire activation handlers are present")
        require("payroll_rules_for_period" in rest and "reconciled" in rest, "payroll rule and allowance reconciliation handlers are wired")
        require("exits/calculate" in rest and "exits/(?P<id>\\d+)/approve" in rest and "exits/(?P<id>\\d+)/pay" in rest, "final-settlement calculation, approval and payment handlers are separate")
        require("Stockland_Accounting::post_journal" in rest and "HR_FINAL_SETTLEMENT" in rest, "final settlement posts through accounting integration")
        require("function hr(){if(!state.hrLifecycleLoaded" in app, "legacy Owner/Management HR view is replaced by the full lifecycle work area")
        for title in ("Workforce planning", "Recruitment and selection", "Contracts, personnel documents and onboarding", "Shifts, attendance and leave", "Compensation, increments, allowances and benefits", "Statutory rule register", "Payroll run, payslips and accounting", "Exit and final settlement"):
            require(title in app, "Owner/Management UI covers " + title)
        require("hrlcLifecycleLists" in app and "loadHRLifecycle" in app and "bindHRLifecycleForms" in app, "full lifecycle UI is bound to the REST handlers")
        require("HRIS lifecycle workspace" in css and "@media(max-width:700px)" in css, "compiled CSS contains responsive HRIS styling")
        require("Approval remains separate" not in app, "no stale/contradictory approval copy")
        require("statutory_rates_seeded" in manifest.get("payroll_integrity", {}) and manifest["payroll_integrity"]["statutory_rates_seeded"] is False, "legal rules are not seeded from unverified sample figures")
        require(manifest.get("payroll_integrity", {}).get("employee_compensation_autofilled") is False, "missing employee compensation is never fabricated")
        require("private-document vault integration" in " ".join(manifest.get("release_gate_note", []) if isinstance(manifest.get("release_gate_note"), list) else [manifest.get("release_gate_note","")).lower(), "release manifest keeps private document and live acceptance gates explicit")
    print("16.1.96 HR & Payroll package QA PASS — this is package/source evidence, not live WordPress/legal sign-off.")

if __name__ == "__main__":
    main()
