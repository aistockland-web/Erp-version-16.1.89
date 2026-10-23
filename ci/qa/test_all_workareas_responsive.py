#!/usr/bin/env python3
"""Smoke-test Stockland's existing work-area shell at phone/desktop widths and role profiles.

The test extracts the exact candidate ZIP to a temporary directory, injects a local-only
navigation hook into that temporary copy, and intercepts all ERP REST calls with fixtures.
It does not contact or modify the live site, production database, or installable archive.
"""
from __future__ import annotations

import argparse
import asyncio
import json
import tempfile
import zipfile
from pathlib import Path

from playwright.async_api import async_playwright

VIEWPORTS = [320, 360, 390, 430, 768, 1024, 1440]
VIEWS = [
    "dashboard", "pos", "daily", "dailyreport", "inventory", "reorder", "reconcile",
    "warehouse", "requests", "transfers", "operate", "wholesale", "returns", "closing",
    "finance", "products", "products-import", "calendar", "users", "settings", "control",
    "hr", "audit", "health", "reports",
]
CAPABILITY_NAMES = [
    "products", "product_import", "reports", "settings", "finance_view", "sale", "warehouse",
    "system_health", "manage_users", "wholesale", "return", "transfer", "request",
    "review_request", "receive_warehouse", "po_approve", "close", "hr_view", "install_schema",
    "daily_import", "request_adjustment", "receive_transfer", "branch_ops", "ledger_view",
    "manage_payroll", "payroll_approve",
]
BASE_USER = {
    "id": 1,
    "name": "Regression Owner",
    "role": "owner",
    "location_name": "Company-wide",
    "branch_location_id": 1,
    "location_ids": [1, 2, 3, 4, 5],
    "can_all": True,
    "can_cost": True,
    "capabilities": {name: True for name in CAPABILITY_NAMES},
}
BOOT = {
    "user": BASE_USER,
    "settings": {
        "business_name": "Stockland Ethiopia",
        "tagline": "Global Brands. Local Access.",
        "vat_rate": 0.15,
        "vat_basis": "INCLUSIVE",
        "branch_reorder_alert_level": 2,
        "central_reorder_alert_level": 200,
    },
    "locations": [
        {"id": 1, "location_name": "Central Warehouse", "location_type": "Warehouse", "is_active": 1},
        {"id": 2, "location_name": "Goro Branch", "location_type": "Branch", "is_active": 1},
        {"id": 3, "location_name": "Gerji Branch", "location_type": "Branch", "is_active": 1},
        {"id": 4, "location_name": "Mexico Branch", "location_type": "Branch", "is_active": 1},
        {"id": 5, "location_name": "Megenagna Branch", "location_type": "Branch", "is_active": 1},
    ],
    "stock": [],
    "products": [],
    "dashboard": {},
    "brand": {},
    "customers": [],
    "suppliers": [],
}


def validate_archive(path: Path) -> tuple[list[str], str]:
    with zipfile.ZipFile(path) as archive:
        bad = archive.testzip()
        if bad:
            raise AssertionError(f"Candidate ZIP CRC failure at {bad}")
        names = [item.filename for item in archive.infolist() if not item.is_dir()]
        if len(names) != 75 or len(names) != len(set(names)):
            raise AssertionError(f"Expected 75 unique plugin files, got {len(names)}")
        if not all(name.startswith("stockland-erp-app/") and ".." not in name.split("/") for name in names):
            raise AssertionError("Candidate has an unsafe path or more than one plugin root")
        js = archive.read("stockland-erp-app/assets/app.js").decode("utf-8")
        manifest = json.loads(archive.read("stockland-erp-app/RELEASE_MANIFEST.json"))
        if "window.__STOCKLAND_TEST__" in js or "__SL_UI_TEST__" in js:
            raise AssertionError("Test hook is present in the installable plugin package")
        fixed = "document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport());return null;"
        old_duplicate_toast = "document.getElementById('retryOpsReport')?.addEventListener('click',()=>loadReport().catch(err=>toast(err.message,true)));throw e;"
        if fixed not in js or old_duplicate_toast in js:
            raise AssertionError("Candidate is missing the single inline report failure/retry behavior")
        return names, str(manifest.get("release", ""))


async def run_candidate(archive_path: Path) -> dict:
    names, release = validate_archive(archive_path)
    with tempfile.TemporaryDirectory(prefix="stockland-all-workareas-") as td:
        root = Path(td)
        with zipfile.ZipFile(archive_path) as archive:
            archive.extractall(root)
        app = root / "stockland-erp-app"
        js_path = app / "assets/app.js"
        js = js_path.read_text(encoding="utf-8")
        startup = "load();})();"
        if js.count(startup) != 1:
            raise AssertionError("Expected one frontend startup marker for temporary test hook")
        hook = (
            "window.__STOCKLAND_TEST__={"
            "setView:function(v){state.view=v;render();},"
            "setUser:function(u){state.boot.user=u;state.view='dashboard';render();},"
            "getNav:function(){return nav();},getState:function(){return state;}};\n"
            + startup
        )
        js_path.write_text(js.replace(startup, hook, 1), encoding="utf-8")

        errors: list[str] = []
        console_errors: list[str] = []
        failures: list[dict] = []
        view_results: list[dict] = []
        role_results: list[dict] = []
        async with async_playwright() as playwright:
            browser = await playwright.chromium.launch(headless=True)
            page = await browser.new_page(viewport={"width": 1440, "height": 1000})
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

            async def mock_api(route):
                relative = route.request.url.split("/wp-json/stockland/")[-1].split("?", 1)[0]
                status, payload = 200, []
                if relative.startswith("v16/setup/status") or relative == "v1/setup/status":
                    payload = {
                        "ready": True, "can_advance": False, "state": "LIVE",
                        "total": {"present": 49, "total": 49},
                        "core": {"present": 29, "total": 29},
                        "finance": {"present": 16, "total": 16},
                        "hr": {"present": 4, "total": 4},
                        "execution_model": "BROWSER_RESUMABLE_BATCHES",
                    }
                elif relative == "v1/app/bootstrap":
                    payload = BOOT
                elif relative.startswith("v1/app/notifications") or relative.startswith("v16/notifications"):
                    payload = []
                elif relative == "v1/app/reports/explorer":
                    payload = {
                        "type": "branch_performance", "title": "Branch performance",
                        "description": "Synthetic visual fixture; not business data.",
                        "from": "2026-10-23", "to": "2026-10-23",
                        "location_label": "All authorized locations",
                        "generated_at": "2026-10-23 12:00:00",
                        "columns": [{"key": "net_sales", "label": "Net sales", "format": "money"}],
                        "rows": [{"net_sales": "100.00"}],
                    }
                elif relative.endswith("/system/health") or relative.endswith("/system/health?mode=deep"):
                    payload = {
                        "ok": True, "php": "8.4.24",
                        "checks": {"db": True, "full_schema": True, "live_ready": True},
                        "diagnostics": {
                            "health_mode": "full", "core_schema_present": 29, "core_schema_total": 29,
                            "full_schema_present": 49, "full_schema_total": 49, "rest_v16_route_count": 104,
                            "execution_model": "BROWSER_RESUMABLE_BATCHES", "database_server": "MariaDB QA fixture",
                            "memory_limit": "512M", "upload_max_filesize": "20M", "post_max_size": "30M",
                        },
                        "failed_checks": [],
                    }
                elif relative.endswith("/app/global-search") or relative.endswith("/sku-search"):
                    payload = []
                elif relative.endswith("/app/access/bootstrap"):
                    payload = {"can_manage_users": False, "accounts": []}
                elif "/app/dashboard" in relative or relative.endswith("/dashboard"):
                    payload = {}
                elif relative.endswith("/app/finance") or relative.endswith("/finance"):
                    payload = {"accounts": [], "invoices": [], "payments": [], "expenses": [], "journals": []}
                elif relative.endswith("/app/hr") or relative.endswith("/hr"):
                    payload = {"employees": [], "compensation": [], "payroll_periods": []}
                await route.fulfill(
                    status=status,
                    content_type="application/json",
                    headers={
                        "access-control-allow-origin": "*",
                        "access-control-allow-headers": "content-type,x-wp-nonce",
                        "access-control-allow-methods": "GET,POST,OPTIONS",
                    },
                    body=json.dumps(payload),
                )

            await page.route("**/wp-json/stockland/**", mock_api)
            await page.set_content(
                '<div id="stockland-app"></div><script>'
                'window.StocklandApp={api:"http://127.0.0.1:8877/wp-json/stockland/v1",'
                'api_v16:"http://127.0.0.1:8877/wp-json/stockland/v16",nonce:"test",'
                f'build:"{release}",logo:"",home:"/",logout:"/",wp_admin:""}};</script>'
            )
            await page.add_style_tag(path=str(app / "assets/bootstrap.min.css"))
            await page.add_style_tag(path=str(app / "assets/app.min.css"))
            await page.add_script_tag(path=str(js_path))
            await page.wait_for_selector("#slGlobalSearch", timeout=10000)
            await page.wait_for_function(
                "window.__STOCKLAND_TEST__ && window.__STOCKLAND_TEST__.getState().boot",
                timeout=8000,
            )

            for width in VIEWPORTS:
                await page.set_viewport_size({"width": width, "height": 900 if width >= 768 else 844})
                for view in VIEWS:
                    await page.evaluate("(v)=>window.__STOCKLAND_TEST__.setView(v)", view)
                    await page.wait_for_timeout(10)
                    snap = await page.evaluate("""() => {
                      const content = document.querySelector('#sl-content .sl-content-inner');
                      const title = content?.querySelector('.sl-page-title h2')?.textContent?.trim() || '';
                      return {
                        viewport: innerWidth,
                        documentWidth: document.documentElement.scrollWidth,
                        bodyWidth: document.body.scrollWidth,
                        title,
                        hasContent: !!content && content.innerHTML.trim().length > 0,
                        scrollContentWidth: content?.scrollWidth || 0,
                        clientContentWidth: content?.clientWidth || 0
                      };
                    }""")
                    if not snap["hasContent"]:
                        failures.append({"type": "blank_view", "width": width, "view": view})
                    if snap["documentWidth"] > width + 1 or snap["bodyWidth"] > width + 1:
                        failures.append({
                            "type": "page_overflow", "width": width, "view": view,
                            "documentWidth": snap["documentWidth"], "bodyWidth": snap["bodyWidth"],
                            "title": snap["title"],
                        })
                    view_results.append({
                        "width": width, "view": view, "title": snap["title"],
                        "overflow": snap["documentWidth"] > width + 1 or snap["bodyWidth"] > width + 1,
                    })

            role_specs = [
                ("sales_employee", {
                    "sale": True, "daily_import": True, "products": False, "finance_view": False,
                    "hr_view": False, "settings": False, "reports": False, "request": True,
                    "transfer": False, "return": False,
                }),
                ("branch_manager", {
                    "sale": True, "daily_import": True, "products": True, "finance_view": False,
                    "hr_view": False, "settings": False, "reports": True, "request": True,
                    "transfer": True, "return": True,
                }),
                ("warehouse_manager", {
                    "sale": False, "daily_import": False, "products": True, "finance_view": False,
                    "hr_view": False, "settings": False, "reports": False, "request": False,
                    "transfer": True, "return": False, "warehouse": True, "receive_warehouse": True,
                }),
                ("management", {
                    "sale": False, "daily_import": False, "products": True, "finance_view": True,
                    "hr_view": True, "settings": True, "reports": True, "request": True,
                    "transfer": True, "return": True,
                }),
                ("employee", {
                    "sale": False, "daily_import": False, "products": False, "finance_view": False,
                    "hr_view": False, "settings": False, "reports": False, "request": False,
                    "transfer": False, "return": False,
                }),
                ("owner", {
                    name: True for name in (
                        "sale", "daily_import", "products", "finance_view", "hr_view", "settings",
                        "reports", "request", "transfer", "return", "warehouse", "receive_warehouse",
                        "wholesale", "system_health", "manage_users", "install_schema",
                    )
                }),
            ]
            for role, overrides in role_specs:
                user = {
                    **BASE_USER,
                    "role": role,
                    "can_all": role == "owner",
                    "can_cost": role in ("owner", "auditor"),
                    "capabilities": {name: False for name in CAPABILITY_NAMES},
                }
                user["capabilities"].update(overrides)
                await page.evaluate("(u)=>window.__STOCKLAND_TEST__.setUser(u)", user)
                await page.wait_for_timeout(15)
                nav = await page.evaluate(
                    "() => [...document.querySelectorAll('#sl-nav [data-view]')].filter(b=>!b.hidden).map(b=>b.dataset.view)"
                )
                checks = {
                    "pos_nav_matches_sale_capability": ("pos" in nav) == bool(user["capabilities"].get("sale")),
                    "product_nav_matches_capability": ("products" in nav) == bool(user["capabilities"].get("products")),
                    "finance_nav_matches_capability": ("finance" in nav) == bool(user["capabilities"].get("finance_view")),
                    "hr_nav_matches_capability": ("hr" in nav) == bool(user["capabilities"].get("hr_view")),
                    "settings_nav_matches_capability": ("settings" in nav) == bool(user["capabilities"].get("settings")),
                    "intelligence_nav_matches_capability": ("intelligence" in nav) == bool(
                        user["capabilities"].get("finance_view") and role in ("owner", "management")
                    ),
                }
                role_results.append({"role": role, "visible_nav": nav, "checks": checks})

                # Refresh Management Intelligence once per eligible profile outside the
                # width/view matrix; repeated refreshes can swamp constrained QA runners.
                await page.evaluate("window.__STOCKLAND_TEST__.setView('intelligence')")
                await page.wait_for_timeout(80)
                intelligence_text = await page.locator("#sl-content").inner_text()
                intelligence_workspace = await page.locator(".sl-management-intelligence").count() > 0
                expected_intelligence = role in ("owner", "management") and bool(user["capabilities"].get("finance_view"))
                if expected_intelligence and not intelligence_workspace:
                    failures.append({"type": "intelligence_view_missing", "role": role, "text": intelligence_text[:200]})
                if not expected_intelligence and "Management Intelligence is restricted" not in intelligence_text:
                    failures.append({"type": "intelligence_access_gate_missing", "role": role, "text": intelligence_text[:200]})
                for check, passed in checks.items():
                    if not passed:
                        failures.append({"type": "role_nav_mismatch", "role": role, "check": check, "nav": nav})
                if role != "owner":
                    await page.evaluate("window.__STOCKLAND_TEST__.setView('wholesale')")
                    await page.wait_for_timeout(10)
                    text = await page.locator("#sl-content").inner_text()
                    if "Owner-only" not in text and "Wholesale access restricted" not in text:
                        failures.append({"type": "wholesale_not_restricted_in_ui", "role": role, "text": text[:200]})

            await browser.close()

        summary = {
            "viewport_view_combinations": len(view_results),
            "role_profiles": len(role_results),
            "overflow_cases": sum(1 for item in view_results if item["overflow"]),
            "failure_count": len(failures),
            "browser_error_count": len(errors),
            "console_error_count": len(console_errors),
        }
        receipt = {
            "result": "PASS" if not failures and not errors and not console_errors else "FAIL",
            "environment": "Local Playwright Chromium with synthetic API fixtures",
            "release": release,
            "runtime_files": len(names),
            "summary": summary,
            "view_results": view_results,
            "role_results": role_results,
            "failures": failures,
            "browser_errors": errors,
            "console_errors": console_errors,
            "test_hooks_added_to_temporary_copy_only": True,
            "live_database_or_business_records_touched": False,
        }
        print(json.dumps(receipt, indent=2))
        if failures or errors or console_errors:
            raise AssertionError("All-workareas responsive smoke gate failed")
        return receipt


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    asyncio.run(run_candidate(args.archive))


if __name__ == "__main__":
    main()
