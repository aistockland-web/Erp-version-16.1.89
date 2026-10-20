#!/usr/bin/env python3
"""Exact 16.1.90 package, provenance, context-safety and schema-preservation checks."""
from __future__ import annotations
import hashlib, json, re, sys, zipfile, tempfile
from pathlib import Path
REPO=Path(__file__).resolve().parents[2]
BASE_SHA="4b8a5fe5648ddf062953c671fe572cb62235757d3a092da6b23116aacae392c3"
ZIP=REPO/"STOCKLAND_ERP_16.1.90_CANONICAL_WORDPRESS_UPDATE.zip"
SIDE=REPO/(ZIP.name+".sha256")
LEGACY=("class-rest-accounting.php","class-rest-app.php","class-rest-calendar.php","class-rest-controller.php","class-rest-hr.php","class-rest-import-v16.php","class-rest-inventory-report-v16.php","class-rest-locations.php","class-rest-products.php","class-rest-receipts.php","class-rest-reports.php","class-rest-requisition-v16.php","class-rest-returns.php","class-rest-sales.php","class-rest-setup.php","class-rest-stock.php","class-rest-transfers.php","class-rest-wholesale.php")
def need(ok,msg):
    print(("PASS " if ok else "FAIL ")+msg)
    if not ok: raise SystemExit(1)
need(ZIP.is_file() and SIDE.is_file(),"canonical 16.1.90 ZIP and checksum sidecar exist")
raw=ZIP.read_bytes(); digest=hashlib.sha256(raw).hexdigest()
need(digest in SIDE.read_text(encoding="utf-8"),"sidecar SHA-256 matches exact ZIP bytes")
with zipfile.ZipFile(ZIP) as z:
    need(z.testzip() is None,"ZIP CRC passes")
    names=z.namelist()
    need(len(names)==74 and len(set(names))==74,"74 unique package files")
    need(all(n.startswith("stockland-erp-app/") and ".." not in Path(n).parts for n in names),"one safe stockland-erp-app/ root")
    with tempfile.TemporaryDirectory(prefix="stockland-1690-check-") as td:
        z.extractall(td); app=Path(td)/"stockland-erp-app"
        plugin=(app/"stockland-inventory.php").read_text()
        front=(app/"includes/class-stockland-frontend.php").read_text()
        m=json.loads((app/"RELEASE_MANIFEST.json").read_text())
        mods=json.loads((app/"assets/css/modules.json").read_text())
        need(bool(re.search(r"Version:\s*16\.1\.90",plugin)) and "define( 'STOCKLAND_VERSION', '16.1.90' );" in plugin,"WordPress header and runtime version are 16.1.90")
        need("const ROUTE_VERSION      = '16.1.90';" in front,"frontend route/cache version is 16.1.90")
        need(m.get("release")=="16.1.90" and mods.get("release")=="16.1.90","release and CSS module manifests match version")
        c=m.get("schema_contract",{})
        need((c.get("core_operations"),c.get("accounting_procurement"),c.get("hr_payroll"),c.get("total"))==(29,16,4,49),"49-table contract remains 29 + 16 + 4")
        need((c.get("core_schema_version"),c.get("accounting_schema_version"),c.get("hr_schema_version"))==("16.1.1","16.1.15","16.1.3"),"all business schema markers unchanged")
        payroll=(app/"includes/class-stockland-payroll-statutory.php").read_text()
        need("ET-PAYE-1395-2025-v3-PENSION-BASE" in payroll,"current PAYE v3 statutory rule retained")
        js=(app/"assets/app.js").read_text()
        need("textContent=msg" in js or "textContent = msg" in js,"frontend displays returned error text without HTML interpretation")
        hr=(app/"includes/rest/class-rest-hr.php").read_text()
        need("esc_html( (string) $validation_error )" not in hr and "esc_html( (string) $treatment )" not in hr,"REST exception messages no longer pre-escape dynamic values")
        need(hr.count("phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped")>=4,"plain-text REST message context is explicitly documented at four throw sites")
        wh=(app/"includes/rest/class-rest-wholesale.php").read_text()
        need("wpdb->prepare( $sql, $args )" in wh and "wpdb->get_results( $prepared, ARRAY_A )" in wh,"wholesale advice still uses prepared SQL")
        need("phpcs:ignore WordPress.DB.DirectDatabaseQuery.NoCaching" in wh,"live stock/commitment query deliberately remains uncached")
        need(sum((app/"includes/rest"/p).read_text().count("phpcs:ignoreFile WordPress.Files.FileName.InvalidClassFileName") for p in LEGACY)==18,"only 18 established REST include paths are exempted from class filename sniff")
        need(len(list(app.rglob("*.php")))==47,"47 PHP files retained")
        # Compare UI runtime assets byte-for-byte against the exact 16.1.89 baseline.
        base_candidates=sorted(REPO.glob("STOCKLAND_ERP_16.1.89_CANONICAL_WORDPRESS_UPDATE*.zip"))
        need(len(base_candidates)==1 and hashlib.sha256(base_candidates[0].read_bytes()).hexdigest()==BASE_SHA,"baseline is the reviewed exact 16.1.89 ZIP")
        with zipfile.ZipFile(base_candidates[0]) as bz:
            bnames=set(bz.namelist())
            for rel in ("assets/app.js","assets/pwa.js","assets/app.css","assets/app.min.css","assets/bootstrap.min.css","assets/stockland-logo.jpg","assets/stockland-sidebar-brand.png","assets/css/01-foundation.css","assets/css/02-shell-navigation.css","assets/css/03-content-data-components.css","assets/css/04-overlays-workflows.css","assets/css/05-responsive-layout.css","assets/css/06-operational-guards.css"):
                name="stockland-erp-app/"+rel
                need(name in bnames and bz.read(name)==(app/rel).read_bytes(),"UI asset preserved byte-for-byte: "+rel)
        need(m.get("corrective_release_16_1_90",{}).get("schema_changed") is False and m.get("corrective_release_16_1_90",{}).get("live_production_deployment") is False,"manifest does not claim schema migration or live deployment")
print("16.1.90 package source-contract validation PASS")
