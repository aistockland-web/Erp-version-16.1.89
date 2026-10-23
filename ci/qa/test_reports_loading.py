#!/usr/bin/env python3
"""Regression-test Reports Explorer error recovery using only in-memory mock API responses.

This QA helper lives outside stockland-erp-app/ and is never included in the installable plugin ZIP.
It never contacts a live WordPress site or changes business data.
"""
from __future__ import annotations

import argparse
import asyncio
import json
import tempfile
import zipfile
from pathlib import Path

from playwright.async_api import async_playwright


BOOT = {
    "user": {
        "id": 1,
        "name": "Regression Owner",
        "role": "owner",
        "location_name": "Company-wide",
        "branch_location_id": 1,
        "location_ids": [1, 2, 3, 4, 5],
        "can_all": True,
        "can_cost": True,
        "capabilities": {
            "products": True,
            "product_import": True,
            "reports": True,
            "settings": True,
            "finance_view": True,
            "sale": True,
            "warehouse": True,
            "system_health": True,
            "manage_users": True,
            "wholesale": True,
            "return": True,
            "transfer": True,
            "request": True,
            "review_request": True,
            "receive_warehouse": True,
            "po_approve": True,
            "close": True,
            "hr_view": True,
            "install_schema": True,
        },
    },
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
}


async def run_candidate(zip_path: Path) -> None:
    with tempfile.TemporaryDirectory(prefix="stockland-report-loading-") as td:
        root = Path(td)
        with zipfile.ZipFile(zip_path) as archive:
            bad_member = archive.testzip()
            if bad_member:
                raise AssertionError(f"Candidate ZIP failed CRC: {bad_member}")
            names = [item.filename for item in archive.infolist() if not item.is_dir()]
            if len(names) != 75 or len(names) != len(set(names)):
                raise AssertionError(f"Unexpected runtime ZIP inventory: {len(names)} files")
            if any(not name.startswith("stockland-erp-app/") for name in names):
                raise AssertionError("Candidate contains a path outside the single production plugin root")
            archive.extractall(root)

        app = root / "stockland-erp-app"
        for needed in ("assets/app.js", "assets/app.min.css", "assets/bootstrap.min.css"):
            if not (app / needed).is_file():
                raise AssertionError(f"Candidate is missing {needed}")

        async with async_playwright() as playwright:
            browser = await playwright.chromium.launch(headless=True)
            page = await browser.new_page(viewport={"width": 1440, "height": 1000})
            page_errors: list[str] = []
            report_requests: list[str] = []
            fail_next_report = {"value": False}
            page.on("pageerror", lambda error: page_errors.append(str(error)))

            async def mock_api(route):
                request = route.request
                relative = request.url.split("/wp-json/stockland/")[-1]
                path_only = relative.split("?", 1)[0]
                if path_only.startswith("v16/setup/status"):
                    payload = {"ready": True, "can_advance": False}
                    status = 200
                elif path_only == "v1/app/bootstrap":
                    payload = BOOT
                    status = 200
                elif path_only.startswith("v1/app/notifications"):
                    payload = []
                    status = 200
                elif path_only == "v1/app/reports/explorer":
                    report_requests.append(request.url)
                    if fail_next_report["value"]:
                        fail_next_report["value"] = False
                        status = 500
                        payload = {
                            "code": "regression_report_source_unavailable",
                            "message": "Report source temporarily unavailable (QA fixture).",
                            "data": {"status": 500},
                        }
                    else:
                        status = 200
                        payload = {
                            "type": "branch_performance",
                            "title": "Branch performance",
                            "description": "Read-only regression fixture, not live ERP data.",
                            "from": "2026-10-23",
                            "to": "2026-10-23",
                            "location_label": "All authorized locations",
                            "generated_at": "2026-10-23 12:00:00",
                            "columns": [
                                {"key": "net_sales", "label": "Net sales", "format": "money"}
                            ],
                            "rows": [{"net_sales": "100.00"}],
                        }
                else:
                    payload = {}
                    status = 200

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
                'api_v16:"http://127.0.0.1:8877/wp-json/stockland/v16",nonce:"test",build:"16.1.93",'
                'logo:"",home:"/",logout:"/",wp_admin:""};</script>'
            )
            await page.add_style_tag(path=str(app / "assets/bootstrap.min.css"))
            await page.add_style_tag(path=str(app / "assets/app.min.css"))
            await page.add_script_tag(path=str(app / "assets/app.js"))

            await page.wait_for_selector("#slGlobalSearch", timeout=8000)
            await page.locator('.sl-nav [data-view="reports"]').click()
            await page.wait_for_selector("#reportForm", timeout=5000)

            # Initial page load succeeds. A user-triggered run then returns a source error;
            # the UI must show one inline error, not a second toast that obscures the report.
            await page.wait_for_selector("#selectedOpsReport", timeout=5000)
            run_button = page.locator("#runOpsReport")
            fail_next_report["value"] = True
            await run_button.click()
            await page.wait_for_selector("#reportResult #retryOpsReport", timeout=5000)
            error_text = await page.locator("#reportResult").inner_text()
            if "Report could not load." not in error_text or "RETRY REPORT" not in error_text:
                raise AssertionError(f"Failed report did not show its recoverable error state: {error_text}")
            if await page.locator("#printOpsReport").is_enabled() or await page.locator("#downloadOpsReportCsv").is_enabled():
                raise AssertionError("Report actions stayed enabled after its source failed")
            toast = page.locator("#toast.bad")
            if await toast.count() and await toast.is_visible():
                raise AssertionError("Report API failure produced a duplicate toast over the inline recovery panel")
            if await run_button.is_disabled():
                raise AssertionError("Run Report stayed disabled after the failed request")
            if await run_button.get_attribute("aria-busy"):
                raise AssertionError("Run Report retained aria-busy after the failed request")
            if (await run_button.inner_text()).strip() != "RUN REPORT":
                raise AssertionError("Run Report label was not restored after the failed request")

            # The inline error and retry action remain within a mobile viewport.
            await page.set_viewport_size({"width": 390, "height": 844})
            await page.wait_for_timeout(100)
            mobile_state = await page.evaluate("""() => {
              const retry = document.getElementById('retryOpsReport');
              const error = document.querySelector('#reportResult .sl-danger-block');
              const rect = node => {
                const r = node.getBoundingClientRect();
                return {left:r.left,right:r.right,width:r.width,top:r.top,bottom:r.bottom};
              };
              return {
                viewport:window.innerWidth,
                scrollWidth:document.documentElement.scrollWidth,
                retry:retry ? rect(retry) : null,
                error:error ? rect(error) : null
              };
            }""")
            if mobile_state["scrollWidth"] > mobile_state["viewport"] + 1:
                raise AssertionError(f"Report error view causes horizontal page overflow on mobile: {mobile_state}")
            for selector, key in (("#retryOpsReport", "retry"), ("#reportResult .sl-danger-block", "error")):
                box = mobile_state[key]
                if not box or box["left"] < -1 or box["right"] > mobile_state["viewport"] + 1:
                    raise AssertionError(f"Mobile {selector} is outside its viewport: {mobile_state}")

            # Retry returns a valid report; the UI must render data and enable report actions.
            await page.locator("#retryOpsReport").click()
            await page.wait_for_selector("#selectedOpsReport", timeout=5000)
            report_text = await page.locator("#selectedOpsReport").inner_text()
            if "Branch performance" not in report_text or "Rows: 1" not in report_text:
                raise AssertionError(f"Retry did not render the returned report: {report_text}")
            if len(report_requests) != 3:
                raise AssertionError(f"Expected initial success, one failed run and one retry, got {len(report_requests)}")
            if await page.locator("#printOpsReport").is_disabled():
                raise AssertionError("Print/Save PDF did not enable after a valid report")
            if await page.locator("#downloadOpsReportCsv").is_disabled():
                raise AssertionError("Selected-report CSV did not enable after a valid report")
            if page_errors:
                raise AssertionError("Uncaught browser exceptions: " + "; ".join(page_errors))

            print(json.dumps({
                "result": "PASS",
                "case": "report request failure -> explicit recoverable error -> retry -> valid report",
                "report_requests": len(report_requests),
                "run_report_restored": True,
                "duplicate_error_toast": False,
                "mobile_error_panel_within_viewport": True,
                "print_enabled_after_success": True,
                "csv_enabled_after_success": True,
                "uncaught_browser_errors": page_errors,
                "zip_runtime_files": len(names),
                "runtime_test_or_staging_copy_added": False,
            }, indent=2))
            await browser.close()


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    asyncio.run(run_candidate(args.archive))


if __name__ == "__main__":
    main()
