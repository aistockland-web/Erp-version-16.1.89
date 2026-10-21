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


def patch_operational_reorder_ui(source: str) -> str:
    """Display the same server-authoritative stock measures in Reorder Alerts."""
    source = replace_literal_once(
        source,
        "const current=Number(x.current_stock||0),level=Math.max(0,Number(x.reorder_level||0));\n   const status=current<=0?'CRITICAL':(current<=level?'REORDER NOW':'OK');\n   const branch=String(x.location_type||'')==='Branch';\n   const canRequest=branch&&!!caps.request&&Number(x.location_id)>0&&status!=='OK';\n   const qty=Math.max(1,Math.ceil(level||1));",
        "const current=Number(x.current_stock||0),reserved=Number(x.reserved_stock||0),available=Number(x.available_stock??Math.max(0,current-reserved)),inbound=Number(x.inbound_stock||0),position=Number(x.reorder_position??(available+inbound)),level=Math.max(0,Number(x.reorder_level||0));\n   const status=String(x.status||'')||(level<=0?'NO_LEVEL':(position<=0?'CRITICAL':(position<=level?'REORDER NOW':'OK')));\n   const branch=String(x.location_type||'')==='Branch';\n   const canRequest=branch&&!!caps.request&&Number(x.location_id)>0&&level>0&&['CRITICAL','REORDER NOW'].includes(status);\n   const suggested=Math.max(0,Number(x.suggested_replenishment??(level>0&&position<=level?Math.max(1,Math.ceil(level-position)):0)));\n   const qty=Math.max(1,Math.ceil(suggested));",
        "reorder UI uses backend status, active reservations and inbound reorder position",
    )
    source = replace_literal_once(
        source,
        "const action=canRequest?'<div class=\"sl-reorder-action\"><label class=\"sl-sr-only\" for=\"reorderQty-'+esc(x.location_id)+'-'+esc(x.variant_id||x.sku)+'\">Requisition quantity for '+esc(x.sku)+'</label><input id=\"reorderQty-'+esc(x.location_id)+'-'+esc(x.variant_id||x.sku)+'\" class=\"sl-reorder-qty\" type=\"number\" min=\"1\" step=\"1\" value=\"'+qty+'\" data-reorder-qty aria-label=\"Requisition quantity for '+esc(x.sku)+'\"><button type=\"button\" class=\"sl-btn sl-btn-sm primary\" data-reorder-create data-location-id=\"'+esc(x.location_id)+'\" data-sku=\"'+esc(x.sku)+'\" data-current=\"'+esc(current)+'\" data-level=\"'+esc(level)+'\">Create requisition</button></div>':'<span class=\"sl-sub\">'+(branch?'Not authorized to request for this branch.':(String(x.location_type||'')==='Warehouse'?'Use the approved purchase-order workflow.':'No action required.'))+'</span>';",
        "const action=canRequest?'<div class=\"sl-reorder-action\"><label class=\"sl-sr-only\" for=\"reorderQty-'+esc(x.location_id)+'-'+esc(x.variant_id||x.sku)+'\">Requisition quantity for '+esc(x.sku)+'</label><input id=\"reorderQty-'+esc(x.location_id)+'-'+esc(x.variant_id||x.sku)+'\" class=\"sl-reorder-qty\" type=\"number\" min=\"1\" step=\"1\" value=\"'+qty+'\" data-reorder-qty aria-label=\"Requisition quantity for '+esc(x.sku)+'\"><button type=\"button\" class=\"sl-btn sl-btn-sm primary\" data-reorder-create data-location-id=\"'+esc(x.location_id)+'\" data-sku=\"'+esc(x.sku)+'\" data-current=\"'+esc(current)+'\" data-reserved=\"'+esc(reserved)+'\" data-available=\"'+esc(available)+'\" data-inbound=\"'+esc(inbound)+'\" data-position=\"'+esc(position)+'\" data-level=\"'+esc(level)+'\">Create requisition</button></div>':'<span class=\"sl-sub\">'+(branch?(level<=0?'Set this branch reorder level in Product Master.':(status==='OK'?'No replenishment required.':'Review this branch and follow the authorization workflow.')):(String(x.location_type||'')==='Warehouse'?'Use the approved purchase-order workflow.':'No action required.'))+'</span>';",
        "reorder action uses configured thresholds and records the calculation inputs",
    )
    source = replace_literal_once(
        source,
        "<th>Location</th><th>SKU / Product</th><th>Current stock</th><th>Reorder level</th><th>Status</th><th>Branch action</th>",
        "<th>Location</th><th>SKU / Product</th><th>Physical on-hand</th><th>Reserved</th><th>Available</th><th>Inbound transfer</th><th>Reorder position</th><th>Reorder level</th><th>Suggested qty</th><th>Status</th><th>Branch action</th>",
        "reorder table headers",
    )
    row_pattern = re.compile(r"""^   return '<tr><td data-label="Location".*?</tr>';\s*$""", re.M)
    new_row = r"""   return '<tr><td data-label="Location"><b>'+esc(x.location_name||'')+'</b><div class="sl-sub">'+esc(x.location_type||'')+'</div></td><td data-label="SKU / Product"><b>'+esc(x.sku||'')+'</b><div class="sl-sub">'+esc(x.product_name||'')+'</div></td><td data-label="Physical on-hand">'+esc(current)+'</td><td data-label="Reserved">'+esc(reserved)+'</td><td data-label="Available"><b>'+esc(available)+'</b>'+(x.availability_exception?'<div><span class="sl-pill bad">'+esc(x.availability_exception)+'</span></div>':'')+'</td><td data-label="Inbound transfer">'+esc(inbound)+'</td><td data-label="Reorder position"><b>'+esc(position)+'</b></td><td data-label="Reorder level">'+esc(level>0?level:'Not set')+'</td><td data-label="Suggested qty">'+esc(suggested)+'</td><td data-label="Status"><span class="sl-pill '+(status==='CRITICAL'?'bad':(status==='REORDER NOW'||status==='NO_LEVEL')?'warn':'ok')+'">'+esc(status)+'</span></td><td data-label="Branch action">'+action+'</td></tr>';"""
    row_matches = list(row_pattern.finditer(source))
    if len(row_matches) != 1:
        raise ValueError(f"Reorder table row: expected exactly one row anchor, got {len(row_matches)}")
    source = row_pattern.sub(lambda _match: new_row, source, count=1)
    source = replace_literal_once(
        source,
        "notes:'Reorder alert: current ledger stock '+button.dataset.current+'; configured reorder level '+button.dataset.level+'. Please review and approve under the existing SOP.'",
        "notes:'Reorder review: physical on-hand '+button.dataset.current+', active reserved '+button.dataset.reserved+', available '+button.dataset.available+', dispatched inbound '+button.dataset.inbound+', reorder position '+button.dataset.position+', configured reorder level '+button.dataset.level+'. Please review and approve under the existing SOP.'",
        "requisition audit context",
    )
    return source


def patch_daily_inventory(source: str) -> str:
    source = replace_literal_once(source,
        "$reorder_qty         = null === $reorder_level ? 0 : max( 0, $reorder_level - $closing );",
        "$reorder_qty         = null === $reorder_level || $reorder_level <= 0 ? 0 : ( $available <= $reorder_level ? max( 1, (int) ceil( max( 0, $reorder_level - $available ) ) ) : 0 );",
        "Daily Inventory reorder quantity uses available stock")
    source = replace_literal_once(source,
        "$status = null === $reorder_level ? 'NO_LEVEL' : ( $reorder_qty > 0 ? ( $warehouse_shortfall > 0 ? 'SHORTFALL' : 'REORDER' ) : 'OK' );",
        "$status = $available <= 0 ? 'CRITICAL' : ( null === $reorder_level || $reorder_level <= 0 ? 'NO_LEVEL' : ( $available <= $reorder_level ? ( $warehouse_shortfall > 0 ? 'SHORTFALL' : 'REORDER' ) : 'OK' ) );",
        "Daily Inventory status uses available stock and missing thresholds")
    source = replace_literal_once(source,
        "if ( $reorder_qty > 0 ) {\n\t\t\t\t\t++$summary['reorder_alerts'];\n\t\t\t\t}",
        "if ( $available <= 0 || ( null !== $reorder_level && $reorder_level > 0 && $available <= $reorder_level ) ) {\n\t\t\t\t\t++$summary['reorder_alerts'];\n\t\t\t\t}",
        "Daily Inventory alerts count zero/negative availability and reorder boundary")
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
                WHERE l.location_type IN('Warehouse','Branch') AND l.is_active=1 AND p.is_active=1 AND p.product_type<>'STYLE'
                    AND (%d = 0 OR l.id=%d)
                ORDER BY reorder_position,p.sku'''


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
\t\t\t\t$location_id ? 1 : 0,
\t\t\t\t$location_id ? (int) $location_id : 0""",
        """\t\t\t\t$products,
\t\t\t\t$locations,
\t\t\t\t$tx,
\t\t\t\t$products,
\t\t\t\t$res,
\t\t\t\t$products,
\t\t\t\tStockland_DB::table( 'transfers' ),
\t\t\t\t$products,
\t\t\t\t$location_id ? 1 : 0,
\t\t\t\t$location_id ? (int) $location_id : 0""",
        "live reorder SQL placeholder order",
    )