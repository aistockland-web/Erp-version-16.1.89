#!/usr/bin/env python3
"""Static source and release invariants exercised against the extracted 16.1.89 ZIP."""
import json
import re
import sys
from pathlib import Path

root = Path(sys.argv[1]).resolve()
def read(relative):
    path = root / relative
    if not path.is_file():
        raise AssertionError(f"Required source missing: {relative}")
    return path.read_text(encoding="utf-8")

manifest = json.loads(read("RELEASE_MANIFEST.json"))
plugin = read("stockland-inventory.php")
frontend = read("includes/class-stockland-frontend.php")
payroll = read("includes/class-stockland-payroll-statutory.php")
rest_app = read("includes/rest/class-rest-app.php")
rest_hr = read("includes/rest/class-rest-hr.php")
appjs = read("assets/app.js")
pwa = read("assets/pwa.js")
schema = manifest["schema_contract"]
checks = []
def check(name, ok):
    checks.append((name, bool(ok)))

check("release identity matches plugin header and constant",
      manifest.get("release") == "16.1.89"
      and re.search(r"Version:\s*16\.1\.89", plugin)
      and "define( 'STOCKLAND_VERSION', '16.1.89' );" in plugin)
check("frontend route/cache marker matches plugin version",
      bool(re.search(r"ROUTE_VERSION\s*=\s*['\"]16\.1\.89['\"]", frontend)))
check("49-table contract preserved (29 + 16 + 4)",
      schema.get("core_operations") == 29 and schema.get("accounting_procurement") == 16
      and schema.get("hr_payroll") == 4 and schema.get("total") == 49
      and schema.get("new_business_table") is False)
check("schema version markers unchanged",
      schema.get("core_schema_version") == "16.1.1"
      and schema.get("accounting_schema_version") == "16.1.15"
      and schema.get("hr_schema_version") == "16.1.3"
      and "define( 'STOCKLAND_DB_VERSION', '16.1.1' );" in plugin)
check("live v3 PAYE policy remains selected",
      "ET-PAYE-1395-2025-v3-PENSION-BASE" in payroll
      and "ET-PAYE-1395-2025-v3-PENSION-BASE" in json.dumps(manifest.get("payroll", {})))
check("payroll and Health share compensation validator",
      "public static function compensation_record_validation_error( $record )" in payroll
      and "Stockland_Payroll_Statutory::compensation_record_validation_error( $compensation_row )" in rest_app
      and "Stockland_Payroll_Statutory::compensation_record_validation_error( $c )" in rest_hr)
check("Health keeps grouped reasons without salary disclosure",
      "'unresolved_reasons'" in rest_app
      and "sl-payroll-readiness-reasons" in appjs
      and "no employee salary or personal details are exposed here." in appjs.lower())
check("payroll-only gate uses scoped operational warning",
      "payrollOnlyIssue?'OPERATIONAL • PAYROLL CHECK REQUIRED'" in appjs)
check("deep integrity remains explicitly guarded/deferred where not executed",
      "supplemental_integrity_scan" in rest_app and "DEFERRED_NOT_RUN" in rest_app)
check("browser theme persistence and all palette IDs are retained",
      "stockland_erp_theme" in appjs
      and all(theme in appjs for theme in ("sapphire", "emerald", "violet", "copper"))
      and "theme-color" in appjs)
check("PWA uses the real install event",
      "beforeinstallprompt" in pwa)
check("database bootstrap and audit/ledger services still exist",
      all((root / p).is_file() for p in (
          "includes/class-stockland-db.php",
          "includes/class-stockland-activator.php",
          "includes/class-stockland-audit.php",
          "includes/class-stockland-ledger.php",
          "includes/class-stockland-authority.php",
          "includes/rest/class-rest-accounting.php",
          "includes/rest/class-rest-transfers.php",
          "includes/rest/class-rest-receipts.php",
          "includes/rest/class-rest-sales.php",
          "includes/rest/class-rest-requisition-v16.php",
      )))
check("developer test files are not accidentally inside release ZIP",
      not any("test-" in p.name.lower() or p.name.lower().startswith("phpunit") for p in root.rglob("*") if p.is_file()))
failed = [name for name, ok in checks if not ok]
for name, ok in checks:
    print(("PASS " if ok else "FAIL ") + name)
print(f"ERP source contracts: {len(checks)-len(failed)}/{len(checks)} PASS")
if failed:
    sys.exit(1)
