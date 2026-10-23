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
    # Reports Explorer already enriches this query with inbound-transfer fields
    # and may add active-location predicates before the closing quote. Match the
    # whole stock query assignment once, then replace it with the canonical
    # legacy-SKU/variant/reservation/in-transit contract below.
    r'\$stock_sql\s*=\s*"SELECT l\.location_name,p\.sku,p\.product_name,p\.brand,p\.size,p\.color,[\s\S]*?WHERE p\.is_active=1[^"]*";'
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
               WHERE t.status='In Transit' AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
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
               WHERE t.status='In Transit' AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
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
               WHERE t.status='In Transit' AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
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
                    WHERE t.status='In Transit' AND COALESCE(t.variant_id,inbound_p.variant_id) IS NOT NULL
                    GROUP BY t.to_location_id,COALESCE(t.variant_id,inbound_p.variant_id)
                ) i ON i.variant_id=p.variant_id AND i.location_id=l.id
                WHERE l.location_type IN('Warehouse','Branch') AND l.is_active=1 AND p.is_active=1 AND p.product_type<>'STYLE'
                    AND (%d = 0 OR l.id=%d)
                ORDER BY reorder_position,p.sku'''



def patch_stock_api(source: str) -> str:
    source = replace_literal_once(source, OLD_STOCK_LIST_SQL, NEW_STOCK_LIST_SQL, "live stock list resolves legacy ledger and reservations")
    source = replace_literal_once(
        source,
        """COALESCE((SELECT SUM(t.quantity_signed) FROM %i t WHERE t.location_id=%d AND (t.variant_id=p.variant_id OR (t.variant_id IS NULL AND t.sku=p.sku))),0)-
\t\t\t\t\t\tCOALESCE((SELECT SUM(r.quantity_reserved) FROM %i r WHERE r.location_id=%d AND r.status='Active' AND (r.variant_id=p.variant_id OR (r.variant_id IS NULL AND r.sku=p.sku))),0) available_stock""",
        """GREATEST(0,COALESCE((SELECT SUM(t.quantity_signed) FROM %i t WHERE t.location_id=%d AND (t.variant_id=p.variant_id OR (t.variant_id IS NULL AND t.sku=p.sku))),0)-
\t\t\t\t\t\tCOALESCE((SELECT SUM(r.quantity_reserved) FROM %i r WHERE r.location_id=%d AND r.status='Active' AND (r.variant_id=p.variant_id OR (r.variant_id IS NULL AND r.sku=p.sku))),0)) available_stock""",
        "SKU detail sellable availability floor",
    )
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
    source = replace_literal_once(
        source,
        """\t\t\t$current       = (float) $row['current_stock'];
\t\t\t$level         = (float) $row['reorder_level'];
\t\t\t$row['status'] = $current <= 0.0 ? 'CRITICAL' : ( $current <= $level ? 'REORDER NOW' : 'OK' );""",
        """\t\t\t$position       = (float) $row['reorder_position'];
\t\t\t$level          = (float) $row['reorder_level'];
\t\t\t$row['status']  = $level <= 0.0 ? 'NO_LEVEL' : ( $position <= 0.0 ? 'CRITICAL' : ( $position <= $level ? 'REORDER NOW' : 'OK' ) );
\t\t\t$row['suggested_replenishment'] = $level > 0.0 && $position <= $level ? max( 1, (int) ceil( max( 0, $level - $position ) ) ) : 0;""",
        "live reorder status evaluates configured level against sellable availability",
    )
    for marker in (
        "OVER-RESERVED",
        "GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))",
        "COALESCE(t.variant_id,legacy_p.variant_id)",
        "reorder_position",
        "suggested_replenishment",
        "t.status='In Transit'",
    ):
        if marker not in source:
            raise ValueError("Live stock API reconciliation marker missing: " + marker)
    if "$row['status']  = $level <= 0.0 ? 'NO_LEVEL' : ( $position <= 0.0 ? 'CRITICAL' : ( $position <= $level ? 'REORDER NOW' : 'OK' ) );" not in source:
        raise ValueError("Live reorder status must use the displayed reorder position and expose unconfigured levels.")
    return source


def patch_php(source: str) -> str:
    source = replace_exactly_once(STOCK_SQL_PATTERN, STOCK_SQL, source, "report inventory SQL")
    # Rebuild the complete report-inventory parameter block so it stays in the same
    # order as STOCK_SQL even if the earlier Reports Explorer patch has already
    # expanded or reformatted the initial argument array.
    stock_args_block = re.compile(
        r"\$stock_args\s*=\s*array\([^;]*\);\s*"
        r"\$stock_sql\s*\.\s*=\s*\$this->append_scope_filter\(\s*'l\.id',\s*\$stock_args,\s*\$scope\s*\);\s*"
        r"if\s*\(\s*''\s*!==\s*\$sku\s*\)\s*\{\s*"
        r"\$stock_sql\s*\.\s*=\s*' AND p\.sku=%s';\s*"
        r"\$stock_args\s*\[\s*\]\s*=\s*\$sku;\s*\}\s*"
        r"\$stock_sql\s*\.\s*=\s*' ORDER BY l\.location_name,p\.sku';",
        re.S,
    )
    stock_args_replacement = """$stock_args = array( $prod, $loc, $tx, $prod, $res, $prod, $tr, $prod );
		$stock_sql .= $this->append_scope_filter( 'l.id', $stock_args, $scope );
		if ( '' !== $sku ) {
			$stock_sql   .= ' AND p.sku=%s';
			$stock_args[] = $sku;
		}
		$stock_sql .= ' ORDER BY l.location_name,p.sku';"""
    stock_args_matches = list(stock_args_block.finditer(source))
    if len(stock_args_matches) != 1:
        current_arrays = re.findall(r"\$stock_args\s*=\s*array\([^;]*;", source)
        raise ValueError(
            "report inventory parameter block: expected exactly one scoped inventory block, "
            f"found {len(stock_args_matches)}; current stock_args assignments={current_arrays!r}"
        )
    source = stock_args_block.sub(lambda _match: stock_args_replacement, source, count=1)
    source = replace_exactly_once(LOW_SQL_PATTERN, LOW_SQL, source, "low-stock SQL")
    low_args_intermediate = "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ) );"
    low_args_canonical = "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'reservations' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'transfers' ), Stockland_DB::table( 'products' ) );"
    if source.count(low_args_intermediate) == 1 and low_args_canonical not in source:
        source = source.replace(low_args_intermediate, low_args_canonical, 1)
    elif source.count(low_args_canonical) == 1 and low_args_intermediate not in source:
        pass
    else:
        raise ValueError("low-stock SQL parameter order: expected exactly one three- or eight-table array")
    source = replace_exactly_once(DASHBOARD_LOW_COUNT_PATTERN, DASHBOARD_LOW_COUNT_SQL, source, "dashboard low-stock counter SQL")
    source = replace_literal_once(
        source,
        "$low_args = array( $prod, $loc, $tx );",
        "$low_args = array( $prod, $loc, $tx, $prod, Stockland_DB::table( 'reservations' ), $prod, Stockland_DB::table( 'transfers' ), $prod );",
        "dashboard low-stock parameter order",
    )

    source = replace_literal_once(
        source,
        """$low_sql .= " AND COALESCE(b.physical_stock,0)<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)";""",
        """$low_sql .= " AND IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)>0 AND GREATEST(0,COALESCE(b.physical_stock,0)-COALESCE(a.reserved_stock,0))<=IF(l.location_type='Warehouse',p.central_reorder_level,p.branch_reorder_level)";""",
        "dashboard low count uses available stock and configured thresholds",
    )


    # Keep physical stock, reserved quantity, sellable available, inbound and
    # reorder position distinct in Reports Explorer. The display label may differ
    # between approved report-source revisions, so key on the canonical field.
    available_column_pattern = re.compile(
        r"array\( 'key' => 'available_stock', 'label' => '[^']+', 'format' => 'number' \),"
    )
    available_column_matches = list(available_column_pattern.finditer(source))
    if len(available_column_matches) != 1:
        raise ValueError("show reservation integrity beside availability: expected exactly one available_stock column")
    source = available_column_pattern.sub(
        lambda match: match.group(0)
        + "\n\t\t\t\t\tarray( 'key' => 'availability_exception', 'label' => 'Reservation integrity' ),"
        + "\n\t\t\t\t\tarray( 'key' => 'inbound_stock', 'label' => 'Inbound transfer', 'format' => 'number' ),"
        + "\n\t\t\t\t\tarray( 'key' => 'reorder_position', 'label' => 'Reorder position', 'format' => 'number' ),",
        source,
        count=1,
    )

    # Reports Explorer's query already provides reorder_position = sellable
    # available + eligible dispatched-but-unreceived transfers. The Owner-only
    # critical report must use that same field for both threshold filtering and
    # the advisory suggestion; comparing only physical/available stock would
    # double-count demand already covered by inbound stock.
    critical_block_pattern = re.compile(
        r"(if\s*\(\s*'critical_reorder'\s*===\s*\$type\s*\)\s*\{)([\s\S]*?)(\n\s*\}\s*\n\s*break;)"
    )
    critical_blocks = list(critical_block_pattern.finditer(source))
    if len(critical_blocks) != 1:
        raise ValueError("critical reorder calculation: expected exactly one Owner-only critical report block")
    critical = critical_blocks[0].group(2)
    available_assignment_pattern = re.compile(
        r"\$available_minor\s*=\s*Stockland_Money::minor\(\s*\$row\['available_stock'\]\s*\?\?\s*'0'\s*\);"
    )
    position_assignment = "$position_minor = Stockland_Money::minor( $row['reorder_position'] ?? $row['available_stock'] ?? '0' );"
    critical, assignment_count = available_assignment_pattern.subn(position_assignment, critical)
    if assignment_count != 2:
        raise ValueError(
            "critical reorder calculation: expected exactly two available_stock assignments, "
            f"updated {assignment_count}; critical block was {critical!r}"
        )
    critical = replace_literal_once(
        critical,
        "return $reorder_minor > 0 && $available_minor <= $reorder_minor;",
        "return $reorder_minor > 0 && $position_minor <= $reorder_minor;",
        "critical report threshold uses inbound-aware reorder position",
    )
    critical = replace_literal_once(
        critical,
        "$row['suggested_replenishment'] = Stockland_Money::decimal( max( 0, $reorder_minor - $available_minor ) );",
        "$row['suggested_replenishment'] = Stockland_Money::decimal( max( 0, $reorder_minor - $position_minor ) );",
        "critical report suggestion uses inbound-aware reorder position",
    )
    source = source[:critical_blocks[0].start(2)] + critical + source[critical_blocks[0].end(2):]

    critical_description_pattern = re.compile(
        r"(if\s*\(\s*'critical_reorder'\s*===\s*\$type\s*\)\s*\{[\s\S]*?\$description\s*=\s*)'[^']*';"
    )
    critical_description_matches = list(critical_description_pattern.finditer(source))
    if len(critical_description_matches) != 1:
        raise ValueError("critical reorder description: expected exactly one description inside the Owner-only critical_reorder branch")
    source = critical_description_pattern.sub(
        r"\1'Owner-only read-only list. Reorder position equals sellable availability plus eligible dispatched-but-unreceived inbound transfers. Inbound does not change physical stock; OVER-RESERVED remains an integrity exception. Suggested replenishment is the non-negative quantity needed to reach the configured location threshold. No stock or purchase order is created.';",
        source,
        count=1,
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
    js_after = patch_operational_reorder_ui(patch_dashboard_js(js_before))
    contents[JS_PATH] = js_after.encode("utf-8")
    daily_path = "stockland-erp-app/includes/rest/class-rest-inventory-report-v16.php"
    daily_before = contents[daily_path].decode("utf-8")
    contents[daily_path] = patch_daily_inventory(daily_before).encode("utf-8")

    manifest = json.loads(contents[MANIFEST_PATH].decode("utf-8"))
    changes = list(manifest.get("release_candidate_changes", []))
    note = (
        "16.1.93 stock consistency: inventory and low-stock reports resolve legacy SKU-only "
        "ledger/reservation rows to their product variant, retain physical/reserved/available "
        "quantities separately, and evaluate reorder against available stock."
    )
    if note not in changes:
        changes.append(note)
    ui_note = "16.1.93 Reorder Alerts and Daily Inventory align displayed quantities, status and advisory suggestions with the reservation-aware ledger calculation."
    if ui_note not in changes:
        changes.append(ui_note)
    manifest["release_candidate_changes"] = changes
    manifest.setdefault("stock_reconciliation", {}).update({
        "physical_stock_source": "immutable stock ledger",
        "legacy_sku_only_ledger_rows": "resolved to active product variant where SKU mapping exists",
        "reservation_scope": "active reservations, including legacy SKU-only rows",
        "availability": "sellable availability is max(0, physical stock minus active reservations); reservation excess remains separately visible",
        "reorder_position": "sellable available quantity plus dispatched-but-not-yet-received internal transfers to the destination; supplier purchase orders are not physical stock before receipt",
        "reorder_basis": "reorder position (sellable available plus eligible in-transit transfers); physical, reserved, available, and inbound remain separate displayed measures",
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

    readme_path = "stockland-erp-app/README.md"
    readme = contents[readme_path].decode("utf-8")
    readme_section = """\n## Reorder and availability — 16.1.93\n\n- Physical on-hand is posted-ledger quantity; active reservations and eligible dispatched inbound transfers remain separate figures.\n- Sellable available quantity is shown independently; over-reservation is explicitly flagged.\n- Reorder position includes sellable available quantity plus eligible dispatched inbound transfers and is compared with the branch or Central Warehouse configured threshold.\n- Daily Inventory evaluates reorder using available quantity and shows zero/negative availability and unconfigured levels distinctly.\n- Suggestions are advisory; opening reports never posts stock movements. Branch requisitions continue through the existing authorization and approval workflow.\n\n"""
    marker = "## Selected Reports & Analytics explorer — 16.1.93"
    if marker not in readme:
        marker = "## Low-resource hosting profile"
    if marker not in readme:
        marker = "## Upgrade procedure"
    if "## Reorder and availability — 16.1.93" not in readme:
        if marker not in readme:
            raise ValueError("README insertion point for the reorder section is missing.")
        contents[readme_path] = readme.replace(marker, readme_section + marker, 1).encode("utf-8")

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
            for required_marker in (
                "OVER-RESERVED",
                "COALESCE(t.variant_id,legacy_p.variant_id)",
                "reorder_position",
                "suggested_replenishment",
                "t.status='In Transit'",
            ):
                if required_marker not in stock_api_final:
                    raise ValueError("Live stock API reconciliation changes were not retained: " + required_marker)
            if "$stock_args = array( $prod, $loc, $tx, $prod, $res, $prod, $tr, $prod );" not in php_final:
                raise ValueError("Report query parameter order not retained.")
            if "$low_args = array( Stockland_DB::table( 'products' ), Stockland_DB::table( 'locations' ), Stockland_DB::table( 'transactions' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'reservations' ), Stockland_DB::table( 'products' ), Stockland_DB::table( 'transfers' ), Stockland_DB::table( 'products' ) );" not in php_final:
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
                "reorder_reporting": "sellable available plus eligible dispatched inbound transfers; physical, reserved, available and inbound are separately displayed",
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
