#!/usr/bin/env python3
"""Apply the consolidated ledger/reservation reconciliation to the 16.1.93 package."""
from __future__ import annotations

import hashlib
import json
import os
import re
import tempfile
import zipfile
from pathlib import Path

PHP_PATH = "stockland-erp-app/includes/rest/class-rest-app.php"
JS_PATH = "stockland-erp-app/assets/app.js"
MANIFEST_PATH = "stockland-erp-app/RELEASE_MANIFEST.json"

STOCK_SQL_PATTERN = re.compile(
    r'\$stock_sql\s*=\s*"SELECT l\.location_name,p\.sku,p\.product_name,p\.brand,p\.size,p\.color,[\s\S]*?WHERE p\.is_active=1";'
)
LOW_SQL_PATTERN = re.compile(
    r'\$low_sql\s*=\s*"SELECT l\.location_name,p\.sku,p\.product_name,[\s\S]*?ORDER BY current_stock,p\.sku";'
)

STOCK_SQL = '''$stock_sql  = "SELECT l.location_name,p.sku,p.product_name,p.brand,p.size,p.color,
                    COALESCE(b.physical_stock,0) physical_stock,
                    COALESCE(a.reserved_stock,0) reserved_stock,
                    GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock,
                    CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED' ELSE '' END availability_exception,
                    COALESCE(i.inbound_stock,0) inbound_stock,
                    GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))+COALESCE(i.inbound_stock,0) reorder_position,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p JOIN %i l ON l.location_type IN('Warehouse','Branch') AND l.is_active=1
             LEFT JOIN (
               SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
               FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
               WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
               GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
             ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (
               SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
               FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
               WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
               GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
             ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
             LEFT JOIN (
               SELECT t.to_location_id location_id,COALESCE(t.variant_id,inbound_p.variant_id) variant_id,
                      SUM(GREATEST(0,COALESCE(t.dispatched_qty,0)-COALESCE(t.received_qty,0))) inbound_stock
               FROM %i t LEFT JOIN %i inbound_p ON t.variant_id IS NULL AND inbound_p.sku=t.sku
               WHERE t.status IN('Picked','In Transit') AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
               GROUP BY t.to_location_id,COALESCE(t.variant_id,inbound_p.variant_id)
             ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
             WHERE p.is_active=1 AND p.product_type<>'STYLE' AND l.is_active=1";'''



LOW_SQL = '''$low_sql  = "SELECT l.location_name,p.sku,p.product_name,
                    COALESCE(b.physical_stock,0) current_stock,
                    COALESCE(b.physical_stock,0) physical_stock,
                    COALESCE(a.reserved_stock,0) reserved_stock,
                    GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock,
                    CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED' ELSE '' END availability_exception,
                    COALESCE(i.inbound_stock,0) inbound_stock,
                    GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))+COALESCE(i.inbound_stock,0) reorder_position,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p CROSS JOIN %i l
             LEFT JOIN (
               SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
               FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
               WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
               GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
             ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (
               SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
               FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
               WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
               GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
             ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
             LEFT JOIN (
               SELECT t.to_location_id location_id,COALESCE(t.variant_id,inbound_p.variant_id) variant_id,
                      SUM(GREATEST(0,COALESCE(t.dispatched_qty,0)-COALESCE(t.received_qty,0))) inbound_stock
               FROM %i t LEFT JOIN %i inbound_p ON t.variant_id IS NULL AND inbound_p.sku=t.sku
               WHERE t.status IN('Picked','In Transit') AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
               GROUP BY t.to_location_id,COALESCE(t.variant_id,inbound_p.variant_id)
             ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
             WHERE p.is_active=1 AND p.product_type<>'STYLE' AND l.is_active=1 AND l.location_type IN('Warehouse','Branch')" . $low_scope_sql . "
             AND IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)>0
             AND GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))+COALESCE(i.inbound_stock,0)<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)
             ORDER BY reorder_position,p.sku";'''



DASHBOARD_LOW_COUNT_PATTERN = re.compile(
    r'\$low_sql\s*=\s*"SELECT COUNT\(\*\) FROM %i p CROSS JOIN %i l LEFT JOIN \(SELECT location_id,variant_id,SUM\(quantity_signed\) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id\) b ON b\.variant_id=p\.variant_id AND b\.location_id=l\.id WHERE p\.is_active=1 AND l\.location_type IN\(\'Warehouse\',\'Branch\'\)";'
)
DASHBOARD_LOW_COUNT_SQL = '''$low_sql  = "SELECT COUNT(*) FROM %i p CROSS JOIN %i l
             LEFT JOIN (
               SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
               FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
               WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
               GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
             ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (
               SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
               FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
               WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
               GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
             ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
             LEFT JOIN (
               SELECT t.to_location_id location_id,COALESCE(t.variant_id,inbound_p.variant_id) variant_id,
                      SUM(GREATEST(0,COALESCE(t.dispatched_qty,0)-COALESCE(t.received_qty,0))) inbound_stock
               FROM %i t LEFT JOIN %i inbound_p ON t.variant_id IS NULL AND inbound_p.sku=t.sku
               WHERE t.status IN('Picked','In Transit') AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
               GROUP BY t.to_location_id,COALESCE(t.variant_id,inbound_p.variant_id)
             ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
             WHERE p.is_active=1 AND p.product_type<>'STYLE' AND l.is_active=1 AND l.location_type IN('Warehouse','Branch')";'''



def replace_exactly_once(pattern: re.Pattern[str], replacement: str, source: str, label: str) -> str:
    matches = list(pattern.finditer(source))
    if len(matches) != 1:
        raise ValueError(f"{label}: expected exactly one SQL anchor, found {len(matches)}")
    return pattern.sub(lambda _match: replacement, source, count=1)

def replace_literal_once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        raise ValueError(f"{label}: expected exactly one anchor, found {count}")
    return source.replace(old, new, 1)


def patch_dashboard_js(source: str) -> str:
    old_available = "Number(r.available_stock??(Number(r.physical_stock||0)-Number(r.reserved_stock||0)))"
    new_available = "Number(r.available_stock??Math.max(0,Number(r.physical_stock||0)-Number(r.reserved_stock||0)))"
    count = source.count(old_available)
    if count != 2:
        raise ValueError(f"Dashboard availability fallback: expected exactly two anchors, found {count}")
    source = source.replace(old_available, new_available)
    source = replace_literal_once(
        source,
        "}).filter(r=>Number(r.physical_stock||0)<=r._reorder).sort((a,b)=>Number(a.physical_stock||0)-Number(b.physical_stock||0)).slice(0,4);",
        "}).filter(r=>r._reorder>0&&r._available<=r._reorder).sort((a,b)=>Number(a._available||0)-Number(b._available||0)).slice(0,4);",
        "dashboard reorder list filters and sorts available stock",
    )
    source = replace_literal_once(
        source,
        "Physical stock is at or below its configured reorder threshold.",
        "Sellable available stock (after active reservations) is at or below its configured reorder threshold.",
        "dashboard reorder alert wording",
    )
    return source


STOCK_API_PATH = "stockland-erp-app/includes/rest/class-rest-stock.php"

OLD_STOCK_LIST_SQL = r'''SELECT v.location_id,v.variant_id,p.sku,p.product_name,p.product_type,p.style_code,p.parent_sku,p.color,p.size,p.barcode,p.gtin,v.physical_stock,COALESCE(r.reserved_stock,0) reserved_stock,v.physical_stock-COALESCE(r.reserved_stock,0) available_stock
				FROM (SELECT t.location_id,t.variant_id,SUM(t.quantity_signed) physical_stock FROM %i t WHERE t.variant_id IS NOT NULL GROUP BY t.location_id,t.variant_id) v
				JOIN %i p ON p.variant_id=v.variant_id
				LEFT JOIN (SELECT location_id,variant_id,sku,SUM(quantity_reserved) reserved_stock FROM %i WHERE status='Active' GROUP BY location_id,variant_id,sku) r ON r.location_id=v.location_id AND r.variant_id=p.variant_id
				WHERE p.is_active=1 AND p.product_type<>'STYLE'
					AND (%d = 0 OR v.location_id=%d)
					AND (%d = 0 OR v.variant_id=%d)
				ORDER BY v.location_id,p.sku'''

NEW_STOCK_LIST_SQL = r'''SELECT v.location_id,v.variant_id,p.sku,p.product_name,p.product_type,p.style_code,p.parent_sku,p.color,p.size,p.barcode,p.gtin,
                v.physical_stock,COALESCE(r.reserved_stock,0) reserved_stock,
                GREATEST(0,v.physical_stock-COALESCE(r.reserved_stock,0)) available_stock,
                CASE WHEN COALESCE(r.reserved_stock,0)>v.physical_stock THEN 'OVER-RESERVED' ELSE '' END availability_exception
				FROM (
                    SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
                    FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
                    WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
                    GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
                ) v
				JOIN %i p ON p.variant_id=v.variant_id
				LEFT JOIN (
                    SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
                    FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
                    WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
                    GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
                ) r ON r.location_id=v.location_id AND r.variant_id=p.variant_id
				WHERE p.is_active=1 AND p.product_type<>'STYLE'
					AND (%d = 0 OR v.location_id=%d)
					AND (%d = 0 OR v.variant_id=%d)
				ORDER BY v.location_id,p.sku'''

OLD_REORDER_SQL = r'''SELECT l.id location_id,l.location_type,l.location_name,p.variant_id,p.sku,p.product_name,p.style_code,p.brand,p.color,p.size,p.barcode,p.gtin,COALESCE(b.physical_stock,0) current_stock,IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
				FROM %i p CROSS JOIN %i l
				LEFT JOIN (SELECT location_id,variant_id,SUM(quantity_signed) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id) b ON b.variant_id=p.variant_id AND b.location_id=l.id
				WHERE l.location_type IN('Warehouse','Branch') AND p.is_active=1 AND p.product_type<>'STYLE'
					AND (%d = 0 OR l.id=%d)
				ORDER BY current_stock,p.sku'''

NEW_REORDER_SQL = r'''SELECT l.id location_id,l.location_type,l.location_name,p.variant_id,p.sku,p.product_name,p.style_code,p.brand,p.color,p.size,p.barcode,p.gtin,
                COALESCE(b.physical_stock,0) current_stock,COALESCE(b.physical_stock,0) physical_stock,
                COALESCE(a.reserved_stock,0) reserved_stock,
                GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock,
                CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED' ELSE '' END availability_exception,
                IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
				FROM %i p CROSS JOIN %i l
				LEFT JOIN (
                    SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
                    FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
                    WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
                    GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
                ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
                LEFT JOIN (
                    SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
                    FROM %i r LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
                    WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
                    GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
                ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
				WHERE l.location_type IN('Warehouse','Branch') AND l.is_active=1 AND p.is_active=1 AND p.product_type<>'STYLE'
					AND (%d = 0 OR l.id=%d)
				ORDER BY available_stock,p.sku'''

def patch_stock_api(source: str) -> str:
    source = replace_literal_once(source, OLD_STOCK_LIST_SQL, NEW_STOCK_LIST_SQL, "live stock list resolves legacy ledger and reservations")
    source = replace_literal_once(
        source,
        """\t\t\t\t$tx,
\t\t\t\t$products,
\t\t\t\t$res,
\t\t\t\t$location_id ? 1 : 0,""",
        """\t\t\t\t$tx,
\t\t\t\t$products,
\t\t\t\t$products,
\t\t\t\t$res,
\t\t\t\t$products,
\t\t\t\t$location_id ? 1 : 0,""",
        "live stock list SQL placeholder order",
    )
    source = replace_literal_once(source, OLD_REORDER_SQL, NEW_REORDER_SQL, "live reorder endpoint uses sellable availability")
    source = replace_literal_once(
        source,
        """\t\t\t\t$products,
\t\t\t\t$locations,
\t\t\t\t$tx,
\t\t\t\t$location_id ? 1 : 0,""",
        """\t\t\t\t$products,
\t\t\t\t$locations,
\t\t\t\t$tx,
\t\t\t\t$products,
\t\t\t\t$res,
\t\t\t\t$products,
\t\t\t\t$location_id ? 1 : 0,""",
        "live reorder SQL placeholder order",
    )
    source = replace_literal_once(
        source,
        """\t\t\t$current       = (float) $row['current_stock'];
\t\t\t$level         = (float) $row['reorder_level'];
\t\t\t$row['status'] = $current <= 0.0 ? 'CRITICAL' : ( $current <= $level ? 'REORDER NOW' : 'OK' );""",
        """\t\t\t$available      = (float) $row['available_stock'];
\t\t\t$level          = (float) $row['reorder_level'];
\t\t\t$row['status']  = $level <= 0.0 ? 'NO_LEVEL' : ( $available <= 0.0 ? 'CRITICAL' : ( $available <= $level ? 'REORDER NOW' : 'OK' ) );""",
        "live reorder status evaluates configured level against sellable availability",
    )
    for marker in ("OVER-RESERVED", "GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))", "COALESCE(t.variant_id,legacy_p.variant_id)"):
        if marker not in source:
            raise ValueError("Live stock API reconciliation marker missing: " + marker)
    return source


def patch_php(source: str) -> str:
    source = replace_exactly_once(STOCK_SQL_PATTERN, STOCK_SQL, source, "report inventory SQL")
    source = replace_literal_once(
        source,
        "$stock_args = array( $prod, $loc, $tx, $res );",
        "$stock_args = array( $prod, $loc, $tx, $prod, $res, $prod, $tr, $prod );",
        "report inventory SQL parameter order",
    )
    source = replace_exactly_once(LOW_SQL_PATTERN, LOW_SQL, source, "low-stock SQL")
    source = replace_literal_once(
        source,
        "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ) );",
        "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'reservations' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'transfers' ), Stockland_DB::table( 'products' ) );",
        "low-stock SQL parameter order",
    )
    source = replace_exactly_once(DASHBOARD_LOW_COUNT_PATTERN, DASHBOARD_LOW_COUNT_SQL, source, "dashboard low-stock counter SQL")
    source = replace_literal_once(
        source,
        "$low_args = array( $prod, $loc, $tx );",
        "$low_args = array( $prod, $loc, $tx, $prod, Stockland_DB::table( 'reservations' ), $prod, Stockland_DB::table( 'transfers' ), $prod );",
        "dashboard low-stock parameter order",
    )
    source = replace_literal_once(
        source,
        r"""$low_sql .= " AND IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)>0 AND GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))+COALESCE(i.inbound_stock,0)<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)";""",
        r"""$low_sql .= " AND IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)>0 AND GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)";""",
        "dashboard reorder compares available stock",
    )

    source = replace_literal_once(
        source,
        "array( 'key' => 'available_stock', 'label' => 'Available', 'format' => 'number' ),",
        "array( 'key' => 'available_stock', 'label' => 'Available', 'format' => 'number' ),\n\t\t\t\t\tarray( 'key' => 'availability_exception', 'label' => 'Reservation integrity' ),",
        "show reservation integrity beside availability",
    )
    source = replace_literal_once(
        source,
        "Available stock equals physical stock less active reservations; suggested replenishment is the minimum quantity needed to reach the configured reorder level.",
        "Sellable availability is max(0, physical stock less active reservations). OVER-RESERVED flags reservations exceeding physical stock; suggested replenishment is based on sellable availability.",
        "critical reorder description",
    )

    source = replace_literal_once(
        source,
        "$inv_sql  = 'SELECT COALESCE(SUM(b.physical_stock*p.purchase_cost),0) FROM (SELECT location_id,variant_id,SUM(quantity_signed) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id) b JOIN %i p ON p.variant_id=b.variant_id';",
        "$inv_sql  = 'SELECT COALESCE(SUM(b.physical_stock*p.purchase_cost),0) FROM (SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock FROM %i t LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)) b JOIN %i p ON p.variant_id=b.variant_id';",
        "dashboard inventory valuation resolves legacy SKU rows",
    )
    source = replace_literal_once(
        source,
        "$inv_args = array( $tx, $prod );",
        "$inv_args = array( $tx, $prod, $prod );",
        "dashboard inventory valuation query parameters",
    )

    # Ensure explicit stock semantics remain present in the source package.
    for required in (
        "COALESCE(t.variant_id,legacy_p.variant_id)",
        "COALESCE(r.variant_id,legacy_r.variant_id)",
        "available_stock",
        "reserved_stock",
    ):
        if required not in source:
            raise ValueError(f"Expected inventory reconciliation marker missing: {required}")
    return source

def rebuild_zip(path: Path) -> dict:
    with zipfile.ZipFile(path, "r") as zin:
        if zin.testzip() is not None:
            raise ValueError("Candidate archive failed its CRC integrity check.")
        infos = zin.infolist()
        names = [i.filename for i in infos if not i.is_dir()]
        if len(names) != 75 or len(names) != len(set(names)):
            raise ValueError(f"Unexpected plugin archive inventory: {len(names)} files.")
        if PHP_PATH not in names or JS_PATH not in names or MANIFEST_PATH not in names or STOCK_API_PATH not in names:
            raise ValueError("Required ERP source, dashboard script, live stock API, or release manifest is missing.")
        contents = {i.filename: zin.read(i.filename) for i in infos if not i.is_dir()}
        info_by_name = {i.filename: i for i in infos}

    php_before = contents[PHP_PATH].decode("utf-8")
    php_after = patch_php(php_before)
    contents[PHP_PATH] = php_after.encode("utf-8")
    stock_api_before = contents[STOCK_API_PATH].decode("utf-8")
    stock_api_after = patch_stock_api(stock_api_before)
    contents[STOCK_API_PATH] = stock_api_after.encode("utf-8")
    js_before = contents[JS_PATH].decode("utf-8")
    js_after = patch_dashboard_js(js_before)
    contents[JS_PATH] = js_after.encode("utf-8")

    manifest = json.loads(contents[MANIFEST_PATH].decode("utf-8"))
    changes = list(manifest.get("release_candidate_changes", []))
    note = (
        "16.1.93 stock consistency: inventory and low-stock reports resolve legacy SKU-only "
        "ledger/reservation rows to their product variant, retain physical/reserved/available "
        "quantities separately, and evaluate reorder against available stock."
    )
    if note not in changes:
        changes.append(note)
    manifest["release_candidate_changes"] = changes
    manifest.setdefault("stock_reconciliation", {}).update({
        "physical_stock_source": "immutable stock ledger",
        "legacy_sku_only_ledger_rows": "resolved to active product variant where SKU mapping exists",
        "reservation_scope": "active reservations, including legacy SKU-only rows",
        "availability": "sellable availability is max(0, physical stock minus active reservations); reservation excess remains separately visible",
        "reorder_basis": "available stock after active reservations",
        "availability_integrity": "reservation excess is displayed as OVER-RESERVED",
        "inventory_valuation_legacy_rows": "SKU-only ledger movements are resolved to canonical variant for dashboard valuation",
        "inventory_valuation_basis": "current Product Master purchase_cost; stock_transactions has no unit_cost column",
        "dashboard_low_stock_ui": "front-end alert list filters and sorts against sellable available quantity, not physical stock",
        "live_stock_api": "variant-linked and legacy SKU-only ledger/reservation rows are combined; available is floored at zero and over-reservation is separately flagged",
        "live_reorder_api": "configured reorder levels are evaluated against sellable available quantity; zero/blank levels return NO_LEVEL",
        "schema_changed": False,
        "live_data_changed": False,
    })
    contents[MANIFEST_PATH] = (json.dumps(manifest, ensure_ascii=False, indent=2) + "\n").encode("utf-8")

    fd, temp_name = tempfile.mkstemp(prefix=path.stem + "-", suffix=".zip", dir=str(path.parent))
    os.close(fd)
    tmp = Path(temp_name)
    try:
        with zipfile.ZipFile(tmp, "w") as zout:
            for info in infos:
                if info.is_dir():
                    zout.writestr(info, b"")
                else:
                    out_info = zipfile.ZipInfo(info.filename, date_time=info.date_time)
                    out_info.compress_type = zipfile.ZIP_DEFLATED
                    out_info.external_attr = info.external_attr
                    out_info.create_system = info.create_system
                    out_info.flag_bits = info.flag_bits
                    zout.writestr(out_info, contents[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
        with zipfile.ZipFile(tmp, "r") as zfinal:
            if zfinal.testzip() is not None:
                raise ValueError("Rebuilt package CRC validation failed.")
            final_names = [i.filename for i in zfinal.infolist() if not i.is_dir()]
            if final_names != names:
                raise ValueError("Package path inventory or order changed.")
            php_final = zfinal.read(PHP_PATH).decode("utf-8")
            stock_api_final = zfinal.read(STOCK_API_PATH).decode("utf-8")
            if "OVER-RESERVED" not in stock_api_final or "COALESCE(t.variant_id,legacy_p.variant_id)" not in stock_api_final:
                raise ValueError("Live stock API reconciliation changes were not retained.")
            if "$stock_args = array( $prod, $loc, $tx, $prod, $res, $prod );" not in php_final:
                raise ValueError("Report query parameter order not retained.")
            if "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'reservations' ), Stockland_DB::table( 'products' ) );" not in php_final:
                raise ValueError("Low-stock query parameter order not retained.")
        before = hashlib.sha256(path.read_bytes()).hexdigest()
        os.replace(tmp, path)
        return {
            "previous_candidate_sha256": before,
            "candidate_sha256": hashlib.sha256(path.read_bytes()).hexdigest(),
            "candidate_bytes": path.stat().st_size,
            "plugin_files": len(names),
            "schema_changed": False,
            "live_data_changed": False,
            "inventory_reporting": "ledger plus active variant-linked and SKU-only legacy records",
                "reorder_reporting": "sellable availability is max of zero and physical stock less active reservations; reservation excess is separately flagged",
            "dashboard_alerts": "server count and front-end list use sellable availability after active reservations, including SKU-only legacy reservation rows",
            "dashboard_low_stock_list": "filter and sort by sellable available stock; availability fallback is floored at zero",
            "live_stock_api": "legacy SKU-only ledger and active reservation rows included; availability exception exposed",
            "live_reorder_api": "reorder status uses sellable availability and ignores unconfigured zero thresholds",
        }
    finally:
        if tmp.exists():
            tmp.unlink()

def main() -> None:
    import argparse
    parser = argparse.ArgumentParser(description="Reconcile inventory/report queries in the ERP release package.")
    parser.add_argument("archive", type=Path)
    args = parser.parse_args()
    print(json.dumps(rebuild_zip(args.archive), indent=2))

if __name__ == "__main__":
    main()
