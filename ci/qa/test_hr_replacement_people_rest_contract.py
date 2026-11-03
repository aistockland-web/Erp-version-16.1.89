#!/usr/bin/env python3
"""Static loader/authority contract for the candidate People REST adapter.

Runs without a WordPress instance. This verifies file mapping and route-level gate
source, not live REST behavior or production-package integration.
"""
from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
RUNTIME = ROOT / "ci" / "hr_payroll_replacement_runtime"
rest_path = RUNTIME / "class-rest-hr-replacement-people.php"
repo_path = RUNTIME / "Stockland_HR_Replacement_People_Repository.php"
service_path = RUNTIME / "Stockland_HR_Replacement_People_Service.php"
access_path = RUNTIME / "Stockland_HR_Replacement_People_WP_Access.php"
contract_path = RUNTIME / "Stockland_HR_Replacement_People_Contract.php"

def require(condition, message):
    if not condition:
        raise SystemExit("FAIL: " + message)

for p in (rest_path, repo_path, service_path, access_path, contract_path):
    require(p.is_file(), "required runtime source exists: " + str(p.relative_to(ROOT)))

rest = rest_path.read_text(encoding="utf-8")
repo = repo_path.read_text(encoding="utf-8")
service = service_path.read_text(encoding="utf-8")
access = access_path.read_text(encoding="utf-8")

includes = re.findall(r"require_once STOCKLAND_PLUGIN_DIR \. 'includes/([^']+)'", rest)
missing = [name for name in includes if not (RUNTIME / name).is_file()]
require(not missing, "every candidate HRX loader include maps to a real runtime source: " + repr(missing))
require("Stockland_HR_Replacement_People_WPDB_Repository" in repo,
        "wpdb repository class lives in the People repository source")
require("Stockland_HR_Replacement_People_WPDB_Repository.php" not in rest,
        "REST loader does not require the nonexistent separate WPDB source file")
require("'application_stage', 'can_prepare_or_approve'" in rest,
        "final stage route allows authorized preparers and Owner checkers through route permission")
require("public function can_prepare_or_approve()" in rest and "return $this->can_prepare() || $this->can_approve();" in rest,
        "route permission delegates to either authorized preparation or Owner decision capability")
require("$requested_final_decision = in_array( $target, array( 'SELECTED', 'NOT_SELECTED', 'ACCEPTED', 'DECLINED' ), true );" in service,
        "service classifies final hiring decisions before record access")
require("if ( $requested_final_decision ) { $this->require_approve(); } else { $this->require_prepare(); }" in service,
        "service enforces separate preparer/checker authorization on stage transitions")
require("$state_field = 'applications' === $entity ? 'stage' : 'status';" in repo,
        "optimistic application updates use the stage column")
require("Stockland_Authority::is_owner()" in access and "Stockland_Authority::can_approve_payroll()" in access,
        "final People approval adapter requires Owner plus payroll approval authority")
require("Stockland_HR_Replacement_Unavailable_Private_Vault" in service,
        "document verification fails closed without an approved private-vault adapter")
print("PASS People REST loader and authority source contracts: includes resolve, Owner-checker route is gated, application optimistic updates use stage. No WordPress instance/live database tested.")
