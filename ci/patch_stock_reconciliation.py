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
                    GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock,\n                    CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED' ELSE '' END availability_exception,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p JOIN %i l ON l.location_type IN('Warehouse','Branch') AND l.is_active=1
             LEFT JOIN (
               SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
               FROM %i t
               LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
               WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
               GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
             ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (
               SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
               FROM %i r
               LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
               WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
               GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
             ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
             WHERE p.is_active=1 AND l.is_active=1";'''

LOW_SQL = '''$low_sql  = "SELECT l.location_name,p.sku,p.product_name,
                    COALESCE(b.physical_stock,0) current_stock,
                    COALESCE(a.reserved_stock,0) reserved_stock,
                    GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0)) available_stock,\n                    CASE WHEN COALESCE(a.reserved_stock,0)>COALESCE(b.physical_stock,0) THEN 'OVER-RESERVED' ELSE '' END availability_exception,
                    IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level) reorder_level
             FROM %i p CROSS JOIN %i l
             LEFT JOIN (
               SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
               FROM %i t
               LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
               WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
               GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
             ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (
               SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
               FROM %i r
               LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
               WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
               GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
             ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
             WHERE p.is_active=1 AND l.is_active=1 AND l.location_type IN('Warehouse','Branch')" . $low_scope_sql . "
             AND IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)>0
             AND GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)
             ORDER BY available_stock,p.sku";'''


DASHBOARD_LOW_COUNT_PATTERN = re.compile(
    r'\$low_sql\s*=\s*"SELECT COUNT\(\*\) FROM %i p CROSS JOIN %i l LEFT JOIN \(SELECT location_id,variant_id,SUM\(quantity_signed\) physical_stock FROM %i WHERE variant_id IS NOT NULL GROUP BY location_id,variant_id\) b ON b\.variant_id=p\.variant_id AND b\.location_id=l\.id WHERE p\.is_active=1 AND l\.location_type IN\(\'Warehouse\',\'Branch\'\)";'
)
DASHBOARD_LOW_COUNT_SQL = '''$low_sql  = "SELECT COUNT(*) FROM %i p CROSS JOIN %i l
             LEFT JOIN (
               SELECT t.location_id,COALESCE(t.variant_id,legacy_p.variant_id) variant_id,SUM(t.quantity_signed) physical_stock
               FROM %i t
               LEFT JOIN %i legacy_p ON t.variant_id IS NULL AND legacy_p.sku=t.sku
               WHERE COALESCE(t.variant_id,legacy_p.variant_id) IS NOT NULL
               GROUP BY t.location_id,COALESCE(t.variant_id,legacy_p.variant_id)
             ) b ON b.variant_id=p.variant_id AND b.location_id=l.id
             LEFT JOIN (
               SELECT r.location_id,COALESCE(r.variant_id,legacy_r.variant_id) variant_id,SUM(r.quantity_reserved) reserved_stock
               FROM %i r
               LEFT JOIN %i legacy_r ON r.variant_id IS NULL AND legacy_r.sku=r.sku
               WHERE r.status='Active' AND COALESCE(r.variant_id,legacy_r.variant_id) IS NOT NULL
               GROUP BY r.location_id,COALESCE(r.variant_id,legacy_r.variant_id)
             ) a ON a.variant_id=p.variant_id AND a.location_id=l.id
             WHERE p.is_active=1 AND l.is_active=1 AND l.location_type IN('Warehouse','Branch')";'''

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

def patch_php(source: str) -> str:
    source = replace_exactly_once(STOCK_SQL_PATTERN, STOCK_SQL, source, "report inventory SQL")
    source = replace_literal_once(
        source,
        "$stock_args = array( $prod, $loc, $tx, $res );",
        "$stock_args = array( $prod, $loc, $tx, $prod, $res, $prod );",
        "report inventory SQL parameter order",
    )
    source = replace_exactly_once(LOW_SQL_PATTERN, LOW_SQL, source, "low-stock SQL")
    source = replace_literal_once(
        source,
        "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ) );",
        "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'reservations' ), Stockland_DB::table( 'products' ) );",
        "low-stock SQL parameter order",
    )
    source = replace_exactly_once(DASHBOARD_LOW_COUNT_PATTERN, DASHBOARD_LOW_COUNT_SQL, source, "dashboard low-stock counter SQL")
    source = replace_literal_once(
        source,
        "$low_args = array( $prod, $loc, $tx );",
        "$low_args = array( $prod, $loc, $tx, $prod, Stockland_DB::table( 'reservations' ), $prod );",
        "dashboard low-stock parameter order",
    )
    source = replace_literal_once(
        source,
        r"""$low_sql .= " AND COALESCE(b.physical_stock,0)<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)";""",
        r"""$low_sql .= " AND IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)>0 AND GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)";""",
        "dashboard reorder compares available stock",
    )

    source = replace_literal_once(
        source,
        "array( 'key' => 'available_stock', 'label' => 'Available', 'format' => 'number' ),",
        "array( 'key' => 'available_stock', 'label' => 'Available', 'format' => 'number' ),\\n\\t\\t\\t\\t\\tarray( 'key' => 'availability_exception', 'label' => 'Reservation integrity' ),",
        "show reservation integrity beside availability",
    )
    source = replace_literal_once(
        source,
        "Available stock equals physical stock less active reservations; suggested replenishment is the minimum quantity needed to reach the configured reorder level.",
        "Sellable availability is max(0, physical stock less active reservations). OVER-RESERVED flags reservations exceeding physical stock; suggested replenishment is based on sellable availability.",
        "critical reorder description",
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
        if PHP_PATH not in names or MANIFEST_PATH not in names:
            raise ValueError("Required ERP source or release manifest is missing.")
        contents = {i.filename: zin.read(i.filename) for i in infos if not i.is_dir()}
        info_by_name = {i.filename: i for i in infos}

    php_before = contents[PHP_PATH].decode("utf-8")
    php_after = patch_php(php_before)
    contents[PHP_PATH] = php_after.encode("utf-8")

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
        "reorder_basis": "available stock after active reservations",\n        "availability_integrity": "reservation excess is displayed as OVER-RESERVED",
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
        "dashboard_alerts": "available stock after active reservations, including SKU-only legacy reservation rows",
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
