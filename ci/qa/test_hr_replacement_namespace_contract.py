#!/usr/bin/env python3
"""Prevent schema/repository table-prefix drift across the new HRX domain."""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
runtime = ROOT / "ci" / "hr_payroll_replacement_runtime"
workforce_repo = (runtime / "Stockland_HR_Replacement_Workforce_WPDB_Repository.php").read_text(encoding="utf-8")
workforce_schema = (runtime / "Stockland_HR_Replacement_Workforce_Schema.php").read_text(encoding="utf-8")
people_repo = (runtime / "Stockland_HR_Replacement_People_Repository.php").read_text(encoding="utf-8")
people_schema = (runtime / "Stockland_HR_Replacement_People_Schema.php").read_text(encoding="utf-8")

def require(ok, message):
    if not ok:
        raise SystemExit("FAIL: " + message)

expected_suffix = "stockland_hrx_"
for label, source in (
    ("workforce repository", workforce_repo),
    ("workforce schema installer", workforce_schema),
    ("People repository", people_repo),
    ("People schema installer", people_schema),
):
    require(expected_suffix in source, label + " must reference the canonical HRX table prefix")
    require("stock_hrx_" not in source, label + " must not use the retired/mismatched stock_hrx_ prefix")

require("return (string) $this->wpdb->prefix . 'stockland_hrx_' . $this->tables[ $entity ];" in workforce_repo,
        "workforce repository maps to the same physical prefix used by its schema installer")
require("return (string) $this->wpdb->prefix . 'stockland_hrx_' . $this->tables[ $entity ];" in people_repo,
        "People repository maps to the same physical prefix used by its schema installer")
for label, source in (("workforce", workforce_repo), ("People", people_repo)):
    require(re.search(r"public function tables_ready\(\).*?'InnoDB'.*?return false;", source, re.S) is not None,
            label + " runtime readiness must reject non-transactional table engines")

require("'migrations'" in workforce_schema and "'events'" in workforce_schema,
        "Stage 3 schema owns the shared migration and event journals")
require("foreach ( array( 'migrations' => 'migrations', 'events' => 'events'" in people_schema,
        "People schema checks the same shared migration/event namespace as prerequisites")
print("PASS HRX namespace contract: schema installers and both repositories use one physical prefix; both DB adapters fail closed on non-InnoDB tables.")
