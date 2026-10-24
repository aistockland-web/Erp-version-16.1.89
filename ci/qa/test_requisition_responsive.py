#!/usr/bin/env python3
"""Responsive regression for requisition approval history; all API data is in-memory.

This runner extracts an installable ZIP to a temporary directory and injects one
test-only frontend hook into that temporary copy. It never contacts or mutates a
live WordPress site, and the hook is never written to the plugin ZIP.
"""
from __future__ import annotations

import argparse
import asyncio
import hashlib
import json
import tempfile
import zipfile
from pathlib import Path

from playwright.async_api import async_playwright

DETAIL = {
    "requisition": {
        "requisition_id": "REQ-2026-000010",
        "location_name": "Mexico Branch",
        "date_requested": "2026-10-22 19:43:58",
        "requester_name": "Mexico Branch Manager",
        "status": "Reviewed",
        "pickup_date": None,
    },
    "items": [
        {
            "id": 1,
            "sku": "STK-0001-10T-BLACK",
            "product_name": "Children's Place Baby Boys And Toddler Boys Pull on Jogger Shorts",
            "quantity_requested": 15,
            "quantity_approved": 0,
            "availability": {"available": 0},
        },
        {
            "id": 2,
            "sku": "STK-0002-XL-NAVY",
            "product_name": "Premium apparel style with an intentionally long descriptive name for layout testing",
            "quantity_requested": 8,
            "quantity_approved": 0,
            "availability": {"available": 3},
        },
    ],
    "approval_lines": [
        {
            "label": "Management",
            "decision": "APPROVE",
            "approved_at": "2026-10-22 19:43:58",
            "notes": "Reviewed and sent for final Owner decision.",
            "can_act": False,
            "stage": "management",
        },
        {
            "label": "Owner",
            "decision": "PENDING",
            "approved_at": "",
            "notes": "Awaiting action",
            "can_act": True,
            "stage": "owner",
        },
    ],
    "availability_checked_at": "2026-10-23 09:30:00",
    "availability": [
        {
            "sku": "STK-0001-10T-BLACK",
            "requested": 15,
            "available": 0,
            "shortage": 15,
            "warehouse_reorder_alert": False,
        }
    ],
    "sourcing_advice": [
        {
            "sku": "STK-0001-10T-BLACK",
            "product_name": "Children's Place Baby Boys And Toddler Boys Pull on Jogger Shorts",
            "status": "SUPPLY_AVAILABLE",
            "requested": 15,
            "branch": {"available": 0},
            "central_available": 500,
            "peer_branch_available": 0,
            "recommended_source": {
                "location_name": "Central Warehouse",
                "location_type": "CENTRAL_WAREHOUSE",
                "available": 500,
                "claimable": 15,
            },
            "sources": [
                {
                    "location_name": "Central Warehouse",
                    "location_type": "CENTRAL_WAREHOUSE",
                    "available": 500,
                    "claimable": 15,
                }
            ],
        }
    ],
    "transfers": [
        {
            "transfer_id": "TRF-2026-000010",
            "sku": "STK-0001-10T-BLACK",
            "quantity": 15,
            "status": "Picked",
            "dispatched_qty": 15,
            "received_qty": 0,
        }
    ],
}

VIEWPORTS = [320, 360, 390, 430, 768, 1024, 1440]


def inspect_archive(archive_path: Path) -> tuple[str, str, int]:
    digest = hashlib.sha256(archive_path.read_bytes()).hexdigest()
    with zipfile.ZipFile(archive_path) as archive:
        bad = archive.testzip()
        if bad:
            raise AssertionError(f"ZIP CRC failure at {bad}")
        files = [item.filename for item in archive.infolist() if not item.is_dir()]
        if len(files) != 75 or len(files) != len(set(files)):
            raise AssertionError(f"Expected exactly 75 unique plugin files, found {len(files)}")
        if not all(name.startswith("stockland-erp-app/") and ".." not in name.split("/") for name in files):
            raise AssertionError("ZIP must contain one safe stockland-erp-app/ root")
        plugin_js = archive.read("stockland-erp-app/assets/app.js").decode("utf-8")
        css = archive.read("stockland-erp-app/assets/css/06-operational-guards.css").decode("utf-8")
        app_css = archive.read("stockland-erp-app/assets/app.css").decode("utf-8")
        app_min = archive.read("stockland-erp-app/assets/app.min.css").decode("utf-8")
        modules = json.loads(archive.read("stockland-erp-app/assets/css/modules.json"))
        manifest = json.loads(archive.read("stockland-erp-app/RELEASE_MANIFEST.json"))
        if manifest.get("release") != modules.get("release"):
            raise AssertionError("Release manifest and CSS module release markers differ")
        if "APPROVAL_HISTORY_SENTINEL_FOR_TEST" in plugin_js:
            raise AssertionError("Test-only sentinel must not be shipped")
        marker = "/* Requisition approval routing — keep decisions readable on phones and desktop."
        if css.count(marker) != 1:
            raise AssertionError("Scoped responsive requisition approval CSS is missing or duplicated")
        chunks = [
            archive.read("stockland-erp-app/assets/css/" + name).decode("utf-8").strip()
            for name in modules["modules"]
        ]
        expected_css = "\n\n".join(chunks) + "\n"
        if expected_css != app_css:
            raise AssertionError("app.css does not match the ordered CSS source modules")
        import re
        normalized = re.sub(r"/\*[\s\S]*?\*/", " ", expected_css)
        normalized = re.sub(r"\s+", " ", normalized).strip()
        normalized = re.sub(r"\s*([{}:;,])\s*", r"\1", normalized).replace(";}", "}")
        if normalized != app_min:
            raise AssertionError("app.min.css does not match deterministic normalized CSS")
    return digest, manifest["release"], len(files)


async def run(archive_path: Path) -> dict:
    digest, release, file_count = inspect_archive(archive_path)
    with tempfile.TemporaryDirectory(prefix="stockland-requisition-responsive-") as td:
        root = Path(td)
        with zipfile.ZipFile(archive_path) as archive:
            archive.extractall(root)
        app = root / "stockland-erp-app"
        js_path = app / "assets/app.js"
        js = js_path.read_text(encoding="utf-8")
        anchor = "load();})();"
        if js.count(anchor) != 1:
            raise AssertionError("Expected exactly one frontend startup anchor for the isolated test hook")
        hook = (
            "window.__SL_UI_TEST__={openReq:openReqDetailV16,"
            "setUser:function(u){state.boot.user=u;},getState:function(){return state;}};\n"
            + anchor
        )
        js_path.write_text(js.replace(anchor, hook, 1), encoding="utf-8")

        role = {
            "id": 1,
            "name": "Owner Test",
            "role": "owner",
            "location_name": "Company-wide",
            "branch_location_id": 1,
            "location_ids": [1, 2, 3, 4, 5],
            "can_all": True,
            "can_cost": True,
            "capabilities": {
                key: True for key in (
                    "products", "product_import", "reports", "settings", "finance_view",
                    "sale", "warehouse", "system_health", "manage_users", "wholesale",
                    "return", "transfer", "request", "review_request", "receive_warehouse",
                    "po_approve", "close", "hr_view", "install_schema", "daily_import",
                    "request_adjustment", "receive_transfer", "branch_ops", "ledger_view",
                    "manage_payroll", "payroll_approve",
                )
            },
        }
        boot = {
            "user": role,
            "settings": {
                "business_name": "Stockland Ethiopia",
                "tagline": "Global Brands. Local Access.",
                "vat_rate": 0.15,
                "vat_basis": "INCLUSIVE",
            },
            "locations": [
                {"id": 1, "location_name": "Central Warehouse", "location_type": "Warehouse", "is_active": 1},
                {"id": 2, "location_name": "Mexico Branch", "location_type": "Branch", "is_active": 1},
            ],
            "stock": [],
            "products": [],
            "dashboard": {},
            "brand": {},
        }

        async with async_playwright() as playwright:
            browser = await playwright.chromium.launch(headless=True)
            page = await browser.new_page(viewport={"width": 390, "height": 844})
            page_errors: list[str] = []
            console_errors: list[str] = []
            page.on("pageerror", lambda error: page_errors.append(str(error)))
            page.on("console", lambda msg: console_errors.append(msg.text) if msg.type == "error" else None)

            async def mock_api(route):
                url = route.request.url
                relative = url.split("/wp-json/stockland/")[-1].split("?", 1)[0]
                if relative.endswith("/setup/status"):
                    payload = {"ready": True, "can_advance": False}
                    status = 200
                elif relative.endswith("/app/bootstrap"):
                    payload, status = boot, 200
                elif relative.endswith("/notifications"):
                    payload, status = [], 200
                elif relative == "v16/requisitions/REQ-2026-000010":
                    payload, status = DETAIL, 200
                elif relative == "v16/requisitions":
                    payload, status = [
                        {
                            "requisition_id": "REQ-2026-000010",
                            "location_name": "Mexico Branch",
                            "requester_name": "Mexico Branch Manager",
                            "date_requested": "2026-10-22 19:43:58",
                            "requested_units": 23,
                            "approved_units": 0,
                            "status": "Reviewed",
                            "pending_action_owner": "Owner / Management",
                            "available_actions": [],
                        }
                    ], 200
                else:
                    payload, status = [], 200
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
            await page.evaluate("window.__SL_UI_TEST__.openReq('REQ-2026-000010')")
            await page.wait_for_selector(".sl-req-drawer", timeout=7000)

            results = []
            for width in VIEWPORTS:
                await page.set_viewport_size({"width": width, "height": 844})
                await page.wait_for_timeout(30)
                state = await page.evaluate("""() => {
                  const rect = node => {
                    const r = node.getBoundingClientRect();
                    return {x:r.x,y:r.y,w:r.width,h:r.height,right:r.right,bottom:r.bottom};
                  };
                  const lines = [...document.querySelectorAll('.sl-approval-line')].map(line => {
                    const person = line.querySelector('.sl-approval-person');
                    const badge = line.querySelector(':scope > div:last-child');
                    const pills = badge ? [...badge.querySelectorAll('.sl-pill')].map(rect) : [];
                    const textNodes = person ? [...person.querySelectorAll('strong,small')].map(rect) : [];
                    return {
                      line:rect(line),person:person?rect(person):null,badge:badge?rect(badge):null,
                      pills,textNodes
                    };
                  });
                  const drawer = document.querySelector('.sl-req-drawer');
                  return {
                    viewport:innerWidth,
                    documentWidth:document.documentElement.scrollWidth,
                    bodyWidth:document.body.scrollWidth,
                    drawer:drawer?rect(drawer):null,
                    gridTemplate:getComputedStyle(document.querySelector('.sl-approval-grid')).gridTemplateColumns,
                    lines,
                    tableWraps:[...(drawer?.querySelectorAll('.sl-table-wrap')||[])].map(rect)
                  };
                }""")
                if state["documentWidth"] > width + 1 or state["bodyWidth"] > width + 1:
                    raise AssertionError(f"{width}px page overflow: {state}")
                if not state["lines"]:
                    raise AssertionError(f"{width}px did not render approval decision rows")
                for line in state["lines"]:
                    for child_name in ("person", "badge"):
                        child = line[child_name]
                        if not child or child["x"] < line["line"]["x"] - 1 or child["right"] > line["line"]["right"] + 1:
                            raise AssertionError(f"{width}px approval {child_name} escapes its row: {state}")
                    for pill in line["pills"]:
                        if pill["x"] < line["line"]["x"] - 1 or pill["right"] > line["line"]["right"] + 1:
                            raise AssertionError(f"{width}px status pill escapes its decision row: {state}")
                    for text_box in line["textNodes"]:
                        for pill in line["pills"]:
                            overlap_x = max(text_box["x"], pill["x"]) < min(text_box["right"], pill["right"])
                            overlap_y = max(text_box["y"], pill["y"]) < min(text_box["bottom"], pill["bottom"])
                            if overlap_x and overlap_y:
                                raise AssertionError(f"{width}px approval text overlaps status pill: {state}")
                for box in state["tableWraps"]:
                    if box["x"] < state["drawer"]["x"] - 1 or box["right"] > state["drawer"]["right"] + 1:
                        raise AssertionError(f"{width}px supply table wrapper escapes the drawer: {state}")
                results.append({
                    "viewport": width,
                    "document_width": state["documentWidth"],
                    "body_width": state["bodyWidth"],
                    "approval_rows": len(state["lines"]),
                    "row_children_within_bounds": True,
                    "status_pills_within_bounds": True,
                    "supply_tables_within_drawer": True,
                })

            await browser.close()
        if page_errors or console_errors:
            raise AssertionError(
                "Frontend errors in responsive approval test: "
                + json.dumps({"page_errors": page_errors, "console_errors": console_errors})
            )
        return {
            "result": "PASS",
            "test": "requisition approval history / supply table mobile and desktop responsive layout",
            "release": release,
            "archive_sha256": digest,
            "runtime_file_count": file_count,
            "viewports": results,
            "page_errors": page_errors,
            "console_errors": console_errors,
            "test_hook_in_package": False,
            "live_site_or_business_data_touched": False,
        }


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    print(json.dumps(asyncio.run(run(args.archive)), indent=2))


if __name__ == "__main__":
    main()
