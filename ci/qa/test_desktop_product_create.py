#!/usr/bin/env python3
"""Desktop regression: prove Product Master create stays clickable under an overlapping host-theme search layer.

All REST calls are mocked in memory. This test never connects to or changes a live ERP.
"""
from __future__ import annotations
import argparse, asyncio, json, tempfile, zipfile
from pathlib import Path
from playwright.async_api import async_playwright

BOOT = {
    "user": {
        "id": 1, "name": "Regression Owner", "role": "owner",
        "location_name": "Company-wide", "branch_location_id": 1,
        "location_ids": [1, 2, 3, 4, 5], "can_all": True, "can_cost": True,
        "capabilities": {
            "products": True, "product_import": True, "reports": True,
            "settings": True, "finance_view": True, "sale": True,
            "warehouse": True, "system_health": True, "manage_users": True,
            "wholesale": True, "return": True, "transfer": True,
            "request": True, "review_request": True, "receive_warehouse": True,
            "po_approve": True, "close": True, "hr_view": True,
            "install_schema": True,
        },
    },
    "settings": {
        "business_name": "Stockland Ethiopia", "tagline": "Global Brands. Local Access.",
        "vat_rate": 0.15, "vat_basis": "INCLUSIVE",
        "branch_reorder_alert_level": 2, "central_reorder_alert_level": 200,
    },
    "locations": [
        {"id": 1, "location_name": "Central Warehouse", "location_type": "Warehouse", "is_active": 1},
        {"id": 2, "location_name": "Goro Branch", "location_type": "Branch", "is_active": 1},
        {"id": 3, "location_name": "Gerji Branch", "location_type": "Branch", "is_active": 1},
        {"id": 4, "location_name": "Mexico Branch", "location_type": "Branch", "is_active": 1},
        {"id": 5, "location_name": "Megenagna Branch", "location_type": "Branch", "is_active": 1},
    ],
    "stock": [], "products": [], "dashboard": {}, "brand": {},
}

async def run_candidate(zip_path: Path) -> None:
    with tempfile.TemporaryDirectory(prefix="stockland-desktop-product-") as td:
        root = Path(td)
        with zipfile.ZipFile(zip_path) as archive:
            bad = archive.testzip()
            if bad:
                raise AssertionError(f"Candidate ZIP integrity failure: {bad}")
            archive.extractall(root)
        app = root / "stockland-erp-app"
        for needed in ("assets/app.js", "assets/app.min.css", "assets/bootstrap.min.css"):
            if not (app / needed).is_file():
                raise AssertionError(f"Candidate missing {needed}")

        async with async_playwright() as p:
            browser = await p.chromium.launch(headless=True)
            checked = []
            for width, height in [(1024, 900), (1280, 900), (1366, 900), (1440, 900), (1920, 1080), (2560, 1440), (3440, 1440)]:
                page = await browser.new_page(viewport={"width": width, "height": height}, device_scale_factor=1)
                errors = []
                created = []
                page.on("pageerror", lambda err: errors.append(str(err)))

                async def mock_api(route):
                    request = route.request
                    url = request.url
                    rel = url.split("/wp-json/stockland/")[-1]
                    if rel.startswith("v16/setup/status"):
                        data = {"ready": True, "can_advance": False}
                    elif rel.startswith("v1/app/bootstrap"):
                        data = BOOT
                    elif rel.split("?")[0] == "v1/products" and request.method == "POST":
                        payload = request.post_data_json or {}
                        data = {"sku": payload.get("sku", ""), "product_name": payload.get("product_name", ""), "product_type": payload.get("product_type", "SIMPLE")}
                        created.append(data)
                    elif rel.startswith("v1/products"):
                        data = []
                    elif rel.startswith("v1/app/global-search"):
                        data = {"results": [{"type": "product", "query": "TCP-1234", "title": "TCP-1234", "subtitle": "Host-theme overlay regression", "target": "products"}]}
                    elif rel.startswith("v1/app/notifications"):
                        data = []
                    else:
                        data = {}
                    await route.fulfill(
                        status=200, content_type="application/json",
                        headers={"access-control-allow-origin": "*", "access-control-allow-headers": "content-type,x-wp-nonce", "access-control-allow-methods": "GET,POST,OPTIONS"},
                        body=json.dumps(data),
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
                await page.locator('.sl-nav [data-view="products"]').click()
                await page.wait_for_selector("#newProduct", timeout=5000)

                # Reproduce the failure class: a host theme stretches the global-search result
                # dropdown down over the page-title actions on wide desktop layouts.
                await page.add_style_tag(content="""
@media (min-width: 992px) {
  #stockland-app .sl-top .sl-global-search-results:not([hidden]) {
    position: fixed !important; left: 0 !important; top: 90px !important;
    width: 100vw !important; height: 240px !important;
    z-index: 2147483647 !important; display: block !important;
    background: #fff !important; border: 2px solid #cf2626 !important;
  }
}
""")
                await page.locator("#slGlobalSearch").fill("TCP")
                await page.wait_for_function(
                    "() => { const p=document.querySelector('#slGlobalSearchResults'); return p && !p.hidden && p.textContent.includes('TCP-1234'); }",
                    timeout=5000,
                )
                box = await page.locator("#newProduct").bounding_box()
                if not box:
                    raise AssertionError(f"{width}px Product Master create button has no visible bounds")

                # Model a WordPress/theme overlay which is painted above the ERP root at desktop size.
                # A physical mouse click on the visible action's coordinates must still open its editor.
                await page.evaluate("""(r) => {
                    const overlay=document.createElement('div');
                    overlay.id='hostThemeActionOverlay';
                    overlay.style.cssText='position:fixed!important;z-index:2147483001!important;left:'+r.x+'px;top:'+r.y+'px;width:'+r.width+'px;height:'+r.height+'px;background:rgba(220,38,38,.08);pointer-events:auto!important;';
                    document.body.appendChild(overlay);
                }""", box)
                hit = await page.evaluate("""() => {
                    const b=document.querySelector('#newProduct');
                    const r=b.getBoundingClientRect();
                    const e=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);
                    return {hitId:e?.id||'', hitClass:String(e?.className||''), x:r.x, y:r.y,
                      actionZ:getComputedStyle(b.parentElement).zIndex,
                      guard:!!document.getElementById('sl-desktop-title-action-layer-fix')};
                }""")
                if hit["hitId"] != "hostThemeActionOverlay":
                    raise AssertionError(f"{width}px desktop overlay reproduction did not cover action: {hit}")
                if not hit["guard"] or int(hit["actionZ"]) < 81:
                    raise AssertionError(f"{width}px desktop action-layer protection missing: {hit}")

                await page.mouse.click(box["x"] + box["width"] / 2, box["y"] + box["height"] / 2)
                await page.wait_for_selector("#productOverlay #productForm", state="visible", timeout=3000)
                if not await page.evaluate("() => typeof document.querySelector('#productForm').onsubmit === 'function'"):
                    raise AssertionError(f"{width}px create form submit handler was not bound")
                sku = f"DTEST-{width}"
                await page.locator('#productForm [name="sku"]').fill(sku)
                await page.locator('#productForm [name="product_name"]').fill(f"Desktop regression {width}px")
                await page.locator("#productSaveButton").click(timeout=3000)
                await page.wait_for_function(
                    "(sku) => !document.getElementById('productOverlay') && document.body.innerText.includes('Product created and verified: '+sku)",
                    arg=sku, timeout=8000,
                )
                if len(created) != 1 or created[0].get("sku") != sku:
                    raise AssertionError(f"{width}px product save chain did not issue one mocked POST: {created}")
                if errors:
                    raise AssertionError(f"{width}px JavaScript errors: {errors}")
                checked.append({"viewport": f"{width}x{height}", "hitTest": "newProduct", "modal": "opened", "submit": "one mocked POST", "errors": 0})
                await page.close()
            await browser.close()
        print(json.dumps({"result": "PASS", "mode": "mocked in-memory REST; no live connection", "viewports": checked}, indent=2))

if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("candidate_zip", type=Path)
    args = parser.parse_args()
    asyncio.run(run_candidate(args.candidate_zip))
