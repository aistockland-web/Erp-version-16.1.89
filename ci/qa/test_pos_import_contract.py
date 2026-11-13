#!/usr/bin/env python3
"""Static contract tests for the three-path Stockland daily POS workflow."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]

def read(path: str) -> str:
    file = ROOT / path
    if not file.is_file():
        raise AssertionError(f"Missing required source: {path}")
    return file.read_text(encoding="utf-8")

imp = read("src/stockland-erp-app/includes/rest/class-rest-import-v16.php")
app = read("src/stockland-erp-app/assets/app.js")
xlsx = read("src/stockland-erp-app/includes/class-stockland-xlsx.php")
runtime = read("src/stockland-erp-app/includes/class-stockland-runtime-diagnostics.php")
setup = read("src/stockland-erp-app/includes/rest/class-rest-setup.php")
agent = read("tools/pos-folder-agent/stockland_pos_folder_agent.py")

checks = []
def check(name: str, condition: bool) -> None:
    checks.append((name, bool(condition)))

check("daily preview, edit and release routes remain distinct", all(
    route in imp for route in ("daily-sales/preview", "daily-sales/commit", "daily-sales/edit-row")
))
check("Sales Employee and Branch Manager upload under role authority",
      "branch_manager', 'sales_employee" in imp and "Stockland_Authority::can_daily_import()" in imp)
check("Branch Manager remains the server-side release authority",
      "Stockland_Authority::can_daily_commit()" in imp)
check("Product Master remains a separate Management/Owner operation",
      "Stockland_Authority::can_product_import()" in imp)
check("three methods are grouped in the Daily POS Control view",
      all(label in app for label in ("MANUAL POS FILE UPLOAD", "BRANCH FOLDER AUTO-IMPORT", "POS API INTEGRATION"))
      and "function dailyImportPage()" in app and "sl-import-purpose-grid" in app)
check("manual UI sends the selected workbook to the canonical preview route",
      "/import/daily-sales/preview" in app and "dailyPosImportForm" in app)
check("folder agent stages through preview and contains no release endpoint",
      "/import/daily-sales/preview" in agent and "/import/daily-sales/commit" not in agent)
check("XLSX reader has bounded ZIP/XML streaming and row/file safety limits",
      "getStream(" in xlsx and "XMLReader" in xlsx
      and "MAX_FILE_BYTES" in xlsx and "MAX_SHEET_XML_BYTES" in xlsx
      and "MAX_PHYSICAL_ROW_NODES" in xlsx)
check("runtime diagnostics reports memory and XLSX runtime capabilities",
      "memory_limit" in runtime and "ZipArchive" in runtime and "XMLReader" in runtime)
check("setup-mode health reports deferred deep checks and explicitly denies live readiness",
      "'deep_integrity_status' => 'DEFERRED_NOT_RUN'" in setup
      and "'full_import_and_workflow_contract_gates'" in setup
      and "$checks['live_ready'] = false;" in setup)
check("daily POS release retains sale-ledger, audit and accounting services",
      "Stockland_Ledger::post_sale_closed" in imp
      and "Stockland_Audit::log" in imp
      and "Stockland_Accounting::post_journal" in imp)

failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(("PASS " if ok else "FAIL ") + name)
print(f"Daily POS source contracts: {len(checks)-len(failed)}/{len(checks)} PASS")
if failed:
    raise SystemExit(1)
