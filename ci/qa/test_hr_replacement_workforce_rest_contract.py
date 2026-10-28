#!/usr/bin/env python3
"""Static security contract for the not-yet-shipped workforce REST adapter."""
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
controller = ROOT / "hr_payroll_replacement_runtime" / "class-rest-hr-replacement-workforce.php"
access = ROOT / "hr_payroll_replacement_runtime" / "Stockland_HR_Replacement_Workforce_WP_Access.php"

def must(condition, message):
    if not condition:
        raise SystemExit("FAIL: " + message)

must(controller.is_file() and access.is_file(), "REST controller and WP authority adapter must exist")
s = controller.read_text(encoding="utf-8")
a = access.read_text(encoding="utf-8")

required_routes = [
    "/hr/replacement/workforce/org-units",
    "/hr/replacement/workforce/plans",
    r"/hr/replacement/workforce/plans/(?P<id>\d+)",
    r"/hr/replacement/workforce/plans/(?P<id>\d+)/lines",
    r"/hr/replacement/workforce/plans/(?P<id>\d+)/lines/(?P<line_id>\d+)",
    r"/hr/replacement/workforce/plans/(?P<id>\d+)/submit",
    r"/hr/replacement/workforce/plans/(?P<id>\d+)/decision",
    "/hr/replacement/workforce/positions",
    r"/hr/replacement/workforce/positions/(?P<id>\d+)/submit",
    r"/hr/replacement/workforce/positions/(?P<id>\d+)/decision",
    r"/hr/replacement/workforce/positions/(?P<id>\d+)/activate",
    "/hr/replacement/workforce/requisitions",
    r"/hr/replacement/workforce/requisitions/(?P<id>\d+)/submit",
    r"/hr/replacement/workforce/requisitions/(?P<id>\d+)/decision",
    r"/hr/replacement/workforce/requisitions/(?P<id>\d+)/open",
]
for route in required_routes:
    must(route in s, "missing REST route: " + route)
must(s.count("register_rest_route(") == len(required_routes), "unexpected or duplicate route registration")
must(len(re.findall(r"(?<!function )idempotency_key\(\s*\$request\s*\)", s)) == 14, "every mutating route must pass an idempotency key to the service")
must(s.count("permission_callback") >= 18, "all route variants must have server-side permission callbacks")
for role_method in ("can_view", "can_prepare", "can_approve", "can_manage_structure"):
    must("array( $this, '" + role_method + "' )" in s, "route must use authority method " + role_method)
must("Stockland_Authority::can_view_hr()" in a, "read access must use Stockland HR authority")
must("Stockland_Authority::can_prepare_hr()" in a, "preparation must use Management-only HR authority")
must("Stockland_Authority::can_approve_payroll()" in a, "final decisions must use Owner approval authority")
must("Stockland_Authority::assert_branch(" in a, "location scope must be server-enforced")
must("Stockland_Authority::company_scope(" in a, "company-wide records must require company scope")
must("new Stockland_HR_Replacement_Workforce_WPDB_Repository()" in s, "controller must use fixed-allowlist repository")
must("new WP_Error(" in s and "hrx_operation_failed" in s, "errors must not silently look like success")
must("error_log( '[Stockland HRX] operation failed (' . get_class( $e ) . ').' )" in s, "unexpected failures must be logged without SQL details")
must("WP_REST_Server::READABLE" in s and "WP_REST_Server::CREATABLE" in s, "read/write HTTP methods must be explicit")
must("return;" in s[:1000], "controller must fail closed when loader prerequisites are unavailable")
must("'/hr/replacement/workforce/plans/(?P<id>\\\\d+)/lines/(?P<line_id>\\\\d+)' in s and "'update_plan_line'" in s,
     "plan-line updates must use the route that includes line_id")
print("PASS workforce REST static contract: 15 unique paths, idempotency, role gates, branch scope, fail-closed errors.")
