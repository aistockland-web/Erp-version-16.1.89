#!/usr/bin/env python3
"""Deterministically harden the exact cumulative 16.1.94 WordPress ZIP into 16.1.95."""
from __future__ import annotations
import argparse, hashlib, json, re, zipfile
from pathlib import Path

ROOT = "stockland-erp-app/"
INPUT_SHA256 = "10f5499291e633e66774d63157317b287e4ff418d95eb5fd97fd5c9e03dbfa23"
SOURCE = "16.1.94"
TARGET = "16.1.95"

def fail(message: str) -> None:
    raise SystemExit("FAIL 16.1.95 patch: " + message)

def once(source: str, old: str, new: str, label: str) -> str:
    count = source.count(old)
    if count != 1:
        fail(f"{label}: expected one exact source anchor, found {count}")
    return source.replace(old, new, 1)

def text_file(files: dict[str, bytes], path: str) -> str:
    key = ROOT + path
    if key not in files:
        fail("missing package file " + key)
    return files[key].decode("utf-8")

def save_text(files: dict[str, bytes], path: str, value: str) -> None:
    files[ROOT + path] = value.encode("utf-8")

def patch(files: dict[str, bytes]) -> None:
    php = text_file(files, "includes/rest/class-rest-app.php")
    php = once(php, "COUNT(*) rows FROM %i WHERE return_date BETWEEN %s AND %s",
               "COUNT(*) AS " + chr(96) + "rows" + chr(96) + " FROM %i WHERE return_date BETWEEN %s AND %s",
               "Reports Explorer rows alias")
    php = once(php, "COUNT(cl.id) lines,SUM(CASE WHEN ABS(cl.variance_qty)>0.00001 THEN 1 ELSE 0 END) variance_lines,",
               "COUNT(cl.id) AS " + chr(96) + "lines" + chr(96) + ",SUM(CASE WHEN ABS(cl.variance_qty)>0.00001 THEN 1 ELSE 0 END) variance_lines,",
               "physical-count lines alias")
    access_method = """\t/**
\t * Allows bootstrap, dashboard insights and search only to administrators or active ERP profiles.
\t *
\t * @return bool Whether the current user has a recognized ERP identity.
\t */
\tprivate function can_access_erp_profile() {
\t\tif ( ! is_user_logged_in() ) {
\t\t\treturn false;
\t\t}
\t\tif ( current_user_can( 'manage_options' ) ) {
\t\t\treturn true;
\t\t}
\t\tif ( ! Stockland_DB::table_exists( Stockland_DB::table( 'users' ) ) ) {
\t\t\treturn false;
\t\t}
\t\treturn true === Stockland_Authority::assert_profile();
\t}

"""
    anchor = "\t/**\n\t * Registers ERP REST endpoints and their permission callbacks."
    if php.count(anchor) != 1:
        fail("could not locate the ERP REST route registration anchor")
    php = php.replace(anchor, access_method + anchor, 1)
    old_permission = "return is_user_logged_in() && ( current_user_can( 'manage_options' ) || Stockland_Authority::assert_profile() );"
    count = php.count(old_permission)
    if count != 4:
        fail(f"ERP bootstrap/search/insights permission callback count was {count}, expected 4")
    php = php.replace(old_permission, "return $this->can_access_erp_profile();")
    save_text(files, "includes/rest/class-rest-app.php", php)

    importer = text_file(files, "includes/rest/class-rest-import-v16.php")
    count = importer.count("AS row_number")
    if count != 5:
        fail(f"Daily POS/Product Master row_number aliases were {count}, expected 5")
    importer = importer.replace("AS row_number", "AS " + chr(96) + "row_number" + chr(96))
    save_text(files, "includes/rest/class-rest-import-v16.php", importer)

    stock = text_file(files, "includes/rest/class-rest-stock.php")
    stock = once(stock,
        "\t\t$tx        = Stockland_DB::table( 'transactions' );\n\t\t$rows      = Stockland_DB::read_results(",
        "\t\t$tx        = Stockland_DB::table( 'transactions' );\n\t\t$res       = Stockland_DB::table( 'reservations' );\n\t\t$rows      = Stockland_DB::read_results(",
        "Stock/Reorder reservations table binding")
    save_text(files, "includes/rest/class-rest-stock.php", stock)

    activator = text_file(files, "includes/class-stockland-activator.php")
    old_expenses = """\t\t\t$expense_loc = (int) Stockland_DB::read_var( $wpdb->prepare( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='location_id'", DB_NAME, $p . 'stock_expenses' ) );
\t\t\tif ( ! $expense_loc ) {
\t\t\t\tStockland_DB::execute_query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i BIGINT UNSIGNED NULL AFTER %i', $p . 'stock_expenses', 'location_id', 'expense_date' ) );
\t\t\t\tStockland_DB::execute_query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i,%i)', $p . 'stock_expenses', 'location_date', 'location_id', 'expense_date' ) );}"""
    new_expenses = """\t\t\t// Accounting tables are installed by later protected setup batches.
\t\t\t$expenses_table = $p . 'stock_expenses';
\t\t\tif ( self::table_exists_static( $expenses_table ) ) {
\t\t\t\t$expense_loc = (int) Stockland_DB::read_var( $wpdb->prepare( "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=%s AND TABLE_NAME=%s AND COLUMN_NAME='location_id'", DB_NAME, $expenses_table ) );
\t\t\t\tif ( ! $expense_loc && $has_col( $expenses_table, 'expense_date' ) ) {
\t\t\t\t\t$added_expense_location = Stockland_DB::execute_query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN %i BIGINT UNSIGNED NULL AFTER %i', $expenses_table, 'location_id', 'expense_date' ) );
\t\t\t\t\tif ( false !== $added_expense_location && ! $has_index( $expenses_table, 'location_date' ) ) {
\t\t\t\t\t\tStockland_DB::execute_query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i,%i)', $expenses_table, 'location_date', 'location_id', 'expense_date' ) );
\t\t\t\t\t}
\t\t\t\t}
\t\t\t}"""
    activator = once(activator, old_expenses, new_expenses, "defer expenses schema alteration")
    save_text(files, "includes/class-stockland-activator.php", activator)

    roles = text_file(files, "includes/class-stockland-roles.php")
    old_roles = """\t\t$users_table = Stockland_DB::table( 'users' );
\t\t$profiles    = Stockland_DB::read_results( $wpdb->prepare( 'SELECT wp_user_id, role, is_active FROM %i', $users_table ), ARRAY_A );
\t\tforeach ( (array) $profiles as $profile ) {"""
    new_roles = """\t\t$users_table = Stockland_DB::table( 'users' );
\t\t// This hook can run before protected setup creates stock_users.
\t\tif ( ! Stockland_DB::table_exists( $users_table ) ) {
\t\t\treturn;
\t\t}
\t\t$wpdb->last_error = '';
\t\t$profiles = Stockland_DB::read_results( $wpdb->prepare( 'SELECT wp_user_id, role, is_active FROM %i', $users_table ), ARRAY_A );
\t\tif ( $wpdb->last_error || ! is_array( $profiles ) ) {
\t\t\tStockland_Runtime_Diagnostics::log_error( 'ERP role synchronization deferred: active profile rows could not be read.' );
\t\t\treturn;
\t\t}
\t\tforeach ( $profiles as $profile ) {"""
    roles = once(roles, old_roles, new_roles, "defer role sync until profile table exists")
    save_text(files, "includes/class-stockland-roles.php", roles)

    accounting = text_file(files, "includes/rest/class-rest-accounting.php")
    old_catch = """\t\t} catch ( Throwable $x ) {
\t\t\tStockland_DB::execute_query( 'ROLLBACK' );
\t\t\tStockland_DB::release_location_locks( $locks['locks'] );
\t\t\t$message = $x->getMessage();
\t\t\t$closed  = ( stripos( $message, 'business date is closed' ) !== false || stripos( $message, 'business day is already closed' ) !== false );
\t\t\treturn new WP_Error( $closed ? 'business_day_closed' : 'receive_failed', $message, array( 'status' => $closed ? 409 : 500 ) );}"""
    new_catch = """\t\t} catch ( Throwable $x ) {
\t\t\tStockland_DB::execute_query( 'ROLLBACK' );
\t\t\tStockland_DB::release_location_locks( $locks['locks'] );
\t\t\t$message      = $x->getMessage();
\t\t\t$closed       = ( stripos( $message, 'business date is closed' ) !== false || stripos( $message, 'business day is already closed' ) !== false );
\t\t\t$over_receipt = ( stripos( $message, 'Received quantity exceeds PO balance' ) !== false );
\t\t\tif ( $closed ) {
\t\t\t\t$code = 'business_day_closed';
\t\t\t} elseif ( $over_receipt ) {
\t\t\t\t$code = 'over_receipt_quantity';
\t\t\t} else {
\t\t\t\t$code = 'receive_failed';
\t\t\t}
\t\t\treturn new WP_Error( $code, $message, array( 'status' => ( $closed || $over_receipt ) ? 409 : 500 ) );}"""
    accounting = once(accounting, old_catch, new_catch, "purchase-order receipt conflict status")
    save_text(files, "includes/rest/class-rest-accounting.php", accounting)

    sales = text_file(files, "includes/rest/class-rest-sales.php")
    old_close = """\t\t$p                  = $request->get_json_params();
\t\t$branch_location_id = $this->resolve_branch_for_user( $p['branch_location_id'] ?? null );
\t\tif ( is_wp_error( $branch_location_id ) ) {
\t\t\treturn $branch_location_id;
\t\t}
\t\t$business_date = sanitize_text_field( $p['business_date'] ?? current_time( 'Y-m-d' ) );"""
    new_close = """\t\t$p = $request->get_json_params();
\t\tif ( ! is_array( $p ) || ! isset( $p['business_date'] ) || ! is_string( $p['business_date'] ) || '' === trim( $p['business_date'] ) ) {
\t\t\treturn new WP_Error( 'business_date_required', 'Select the exact business_date to close. The server will not infer a business date for a closing operation.', array( 'status' => 400 ) );
\t\t}
\t\t$branch_location_id = $this->resolve_branch_for_user( $p['branch_location_id'] ?? null );
\t\tif ( is_wp_error( $branch_location_id ) ) {
\t\t\treturn $branch_location_id;
\t\t}
\t\t$business_date = sanitize_text_field( $p['business_date'] );"""
    sales = once(sales, old_close, new_close, "explicit daily-close date")
    save_text(files, "includes/rest/class-rest-sales.php", sales)

    setup = text_file(files, "includes/rest/class-rest-setup.php")
    setup = once(setup,
        "\tprivate function can_view_status() {\n\t\treturn is_user_logged_in();\n\t}",
        "\tprivate function can_view_status() {\n\t\tif ( ! is_user_logged_in() ) {\n\t\t\treturn false;\n\t\t}\n\t\tif ( current_user_can( 'manage_options' ) || current_user_can( 'stockland_install_schema' ) ) {\n\t\t\treturn true;\n\t\t}\n\t\tif ( ! Stockland_DB::table_exists( Stockland_DB::table( 'users' ) ) ) {\n\t\t\treturn false;\n\t\t}\n\t\treturn true === Stockland_Authority::assert_profile();\n\t}",
        "setup status access scope")
    save_text(files, "includes/rest/class-rest-setup.php", setup)

    app_js = text_file(files, "assets/app.js")
    start = app_js.find("function resolveTrustedApiUrl(path){")
    end = app_js.find("function setupProgressCard(", start)
    if start < 0 or end <= start:
        fail("could not find exact REST URL resolver replacement boundaries")
    resolver = """function resolveTrustedApiUrl(path,baseOverride){
  const raw=String(path??'');let target;
  try{
    if(/^https?:\\/\\//i.test(raw)){target=new URL(raw,window.location.href)}else{
      const base=new URL(String(baseOverride||A.api||''),window.location.href),q=raw.indexOf('?');
      const route=(q>=0?raw.slice(0,q):raw).replace(/^\\/+|\\/+$/g,'');if(!route)throw Error('empty route');
      if(base.searchParams.has('rest_route')){const rb=String(base.searchParams.get('rest_route')||'').replace(/\\/+$/g,'');if(!rb)throw Error('empty REST route base');base.searchParams.set('rest_route',rb+'/'+route)}
      else base.pathname=base.pathname.replace(/\\/+$/g,'')+'/'+route;
      if(q>=0)new URLSearchParams(raw.slice(q+1)).forEach((v,k)=>base.searchParams.append(k,v));target=base;
    }
  }catch(_){throw new Error('ERP request URL is invalid. No ERP data was changed.')}
  const derivedV16=String(A.api||'').replace(/\\/v1$/,'/v16');const bases=[A.api,A.api_v16,derivedV16].filter(Boolean);
  const allowed=bases.some(v=>{let b;try{b=new URL(String(v),window.location.href)}catch(_){return false}if(target.origin!==b.origin)return false;
    const br=b.searchParams.get('rest_route'),tr=target.searchParams.get('rest_route');
    if(br!==null){const expected=br.replace(/\\/+$/,'');return typeof tr==='string'&&(tr===expected||tr.indexOf(expected+'/')===0)}
    const prefix=b.pathname.replace(/\\/+$/,'');return target.pathname===prefix||target.pathname.indexOf(prefix+'/')===0});
  if(!allowed)throw new Error('Blocked ERP request outside the configured WordPress REST API. No ERP data was changed.');return target.href;
}
async function api(path,opt={},baseOverride){const headers=Object.assign({'X-WP-Nonce':A.nonce},opt.headers||{});const multipart=typeof FormData!=='undefined'&&opt.body instanceof FormData;if(multipart){Object.keys(headers).forEach(k=>{if(k.toLowerCase()==='content-type')delete headers[k]})}else if(!Object.keys(headers).some(k=>k.toLowerCase()==='content-type')){headers['Content-Type']='application/json'}opt.headers=headers;const url=resolveTrustedApiUrl(path,baseOverride);let controller=null;let timer=null;if(!opt.signal){controller=new AbortController();opt.signal=controller.signal;timer=setTimeout(()=>controller.abort(),25000)}if(!opt.cache && (!opt.method || String(opt.method).toUpperCase()==='GET'))opt.cache='no-store';try{return await parseApiResponse(await fetch(url,opt))}catch(e){if(e?.name==='AbortError')throw new Error('ERP request timed out. No ERP data was changed. Please retry after checking System Health.');if(e instanceof TypeError && /failed to fetch/i.test(String(e.message||'')))throw new Error('ERP connection failed. The server could not be reached and no ERP data was changed. Check the ERP URL/hosting connection and retry.');throw e}finally{if(timer)clearTimeout(timer)}}
"""
    app_js = app_js[:start] + resolver + app_js[end:]
    app_js = once(app_js,
        "    const url=String(base).replace(/\\/$/,'')+pth;\n    return api(url,cfg);",
        "    return api(pth,cfg,base);",
        "REST URL builder query preservation")
    save_text(files, "assets/app.js", app_js)

    entry = text_file(files, "stockland-inventory.php")
    entry = once(entry, "Version:           16.1.94", "Version:           16.1.95", "plugin header version")
    entry = once(entry, "define( 'STOCKLAND_VERSION', '16.1.94' );", "define( 'STOCKLAND_VERSION', '16.1.95' );", "plugin runtime version")
    save_text(files, "stockland-inventory.php", entry)
    frontend = text_file(files, "includes/class-stockland-frontend.php")
    frontend = once(frontend, "const ROUTE_VERSION      = '16.1.94';", "const ROUTE_VERSION      = '16.1.95';", "frontend route/cache identity")
    save_text(files, "includes/class-stockland-frontend.php", frontend)

    manifest = json.loads(text_file(files, "RELEASE_MANIFEST.json"))
    if manifest.get("release") != SOURCE or manifest.get("schema_contract", {}).get("total") != 49:
        fail("expected 16.1.94 manifest and exact 49-table contract")
    manifest["release"] = TARGET
    manifest["release_label"] = "Stockland Ethiopia ERP — cumulative WordPress update 16.1.95"
    manifest["baseline"] = SOURCE
    manifest["classification"] = "Cumulative operational hardening from the verified 16.1.94 package. Fixes reserved SQL aliases, Stock/Reorder query binding, safe first activation, plain-permalink REST query construction, profile-gated search/setup status, explicit day-close dates and PO over-receipt status. Preserves the 49-table schema and historical business data."
    manifest["release_changes"] = [
        "Cumulative update from 16.1.94; release/cache identity advances to 16.1.95.",
        "Reports Summary/Explorer quote reserved rows and lines SQL aliases; five import queries quote row_number.",
        "Stock/Reorder binds the reservations table explicitly.",
        "First activation defers ERP profile sync until stock_users exists and prevents ALTER TABLE against absent stock_expenses.",
        "REST URL construction preserves query keys on plain-permalink and pretty-permalink WordPress sites.",
        "Application bootstrap, dashboard insights, SKU/global search and setup status require WordPress administration or an active Stockland ERP profile.",
        "Day close requires an explicit business_date; the server never infers a date for the closing operation.",
        "PO over-receipt returns HTTP 409; unexpected receiving/server errors remain 500 and failed posting rolls back.",
        "Management Intelligence is deterministic, source-backed ERP analytics; no external Cloud AI provider is configured or called.",
        "No schema migration, sample business data, compensation values or live database mutations are performed by the build."
    ]
    ui = manifest.setdefault("user_interface", {}).setdefault("management_intelligence", {})
    ui["cloud_ai_boundary"] = {
        "external_provider_configured": False,
        "external_ai_requests_from_plugin": False,
        "current_behavior": "Source-backed deterministic analytics from authorized ERP insights; not an external generative AI integration.",
        "release_policy": "Do not indicate a connected Cloud AI service or send ERP data externally without a configured, owner-approved integration and data policy."
    }
    manifest["security_hardening_16_1_95"] = {
        "explicit_setup_status_access": True,
        "profile_gated_bootstrap_search_and_insights": True,
        "plain_permalink_query_parameters_preserved": True,
        "day_close_requires_business_date": True,
        "purchase_order_over_receipt_conflict_status": 409,
        "business_data_mutated_by_build": False,
        "schema_changed_by_build": False
    }
    manifest["release_gate_note"] = "Exact-package CI, source/security coverage and local regression results are external receipts. Owner-provided System Health is documented separately. A passing package check does not replace backup/restore, exact installed-source parity, the complete deep-integrity run or independent statutory payroll acceptance."
    save_text(files, "RELEASE_MANIFEST.json", json.dumps(manifest, ensure_ascii=False, indent=2) + "\n")

    css_manifest = json.loads(text_file(files, "assets/css/modules.json"))
    if css_manifest.get("release") != SOURCE or len(css_manifest.get("modules", [])) != 6:
        fail("expected six-module CSS manifest at 16.1.94")
    css_manifest["release"] = TARGET
    css_manifest["description"] = "16.1.95 release/cache identity for the cumulative Stockland ERP update. Existing six ordered CSS modules are retained without changing presentation source."
    save_text(files, "assets/css/modules.json", json.dumps(css_manifest, ensure_ascii=False, indent=2) + "\n")

    readme = text_file(files, "README.md")
    if not readme.startswith("# Stockland Ethiopia ERP — WordPress plugin update 16.1.94"):
        fail("README title does not match 16.1.94 source")
    cut = readme.find("## Update the installed WordPress plugin")
    if cut < 0:
        fail("README update instructions heading missing")
    head = """# Stockland Ethiopia ERP — WordPress plugin update 16.1.95

## Release 16.1.95 — cumulative operational hardening from 16.1.94

This update corrects reserved SQL aliases in reports and imports, the missing reservations-table binding, first-activation ordering guards, plain-permalink REST URL handling, profile-less search/setup access, implicit day-close dates, and purchase-order over-receipt status. Existing stock and accounting posting logic remains the source of truth. The 49-table contract and DB schema markers are retained; the build does not mutate business records.

**Cloud AI status:** Management Intelligence provides permission-controlled, source-backed ERP analytics. No external Cloud AI/LLM provider is configured or called by this plugin. Do not send employee, financial, customer, or inventory data to an external provider until Stockland has approved the provider, data-handling rules, and integration credentials.

**Owner-provided System Health (24 October 2026):** the supplied screen reports WordPress 7.1.3, PHP 8.4.27, MariaDB 11.4.13, 49/49 tables, 104 REST routes, a passing audit-chain check and clean supplemental integrity counts. Nine active employee compensation records remain unresolved. The complete deep-integrity action, exact installed-file checksum match, backup/restore and rollback are still separate acceptance requirements.

This release does not create sample employees, fill salary values, change historical postings or weaken the payroll gate. The package must be verified against the exact installed files before production replacement.

"""
    save_text(files, "README.md", head + readme[cut:])

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--receipt", type=Path, required=True)
    args = parser.parse_args()
    if not args.input.is_file():
        fail("16.1.94 input ZIP missing")
    source_hash = hashlib.sha256(args.input.read_bytes()).hexdigest()
    if source_hash != INPUT_SHA256:
        fail(f"16.1.94 input SHA-256 mismatch: {source_hash}")
    with zipfile.ZipFile(args.input) as zin:
        if zin.testzip() is not None:
            fail("input ZIP CRC failed")
        infos = [i for i in zin.infolist() if not i.is_dir()]
        names = [i.filename for i in infos]
        if len(names) != 75 or len(set(names)) != 75 or not all(n.startswith(ROOT) and ".." not in n.split("/") for n in names):
            fail("input must contain 75 unique files in one safe plugin root")
        files = {i.filename: zin.read(i.filename) for i in infos}
        before = dict(files)
    patch(files)
    output_manifest = json.loads(files[ROOT + "RELEASE_MANIFEST.json"].decode("utf-8"))
    output_entry = files[ROOT + "stockland-inventory.php"].decode("utf-8")
    output_front = files[ROOT + "includes/class-stockland-frontend.php"].decode("utf-8")
    if output_manifest.get("release") != TARGET or output_manifest.get("schema_contract", {}).get("total") != 49:
        fail("final release/schema marker incorrect")
    if "Version:           16.1.95" not in output_entry or "define( 'STOCKLAND_VERSION', '16.1.95' );" not in output_entry:
        fail("plugin version markers inconsistent")
    if "const ROUTE_VERSION      = '16.1.95';" not in output_front:
        fail("route/cache version marker inconsistent")
    changed = sorted(n for n in names if before[n] != files[n])
    expected = sorted(ROOT + x for x in [
        "README.md", "RELEASE_MANIFEST.json", "assets/app.js", "assets/css/modules.json",
        "includes/class-stockland-activator.php", "includes/class-stockland-frontend.php",
        "includes/class-stockland-roles.php", "includes/rest/class-rest-accounting.php",
        "includes/rest/class-rest-app.php", "includes/rest/class-rest-import-v16.php",
        "includes/rest/class-rest-sales.php", "includes/rest/class-rest-setup.php",
        "includes/rest/class-rest-stock.php", "stockland-inventory.php"
    ])
    if changed != expected:
        fail("changed-file allowlist mismatch: " + repr(changed))
    if args.output.resolve() == args.input.resolve():
        fail("output must be distinct; baseline is never overwritten")
    args.output.parent.mkdir(parents=True, exist_ok=True)
    with zipfile.ZipFile(args.output, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as zout:
        for info in infos:
            zi = zipfile.ZipInfo(info.filename, date_time=info.date_time)
            zi.compress_type = zipfile.ZIP_DEFLATED
            zi.external_attr = info.external_attr
            zi.create_system = info.create_system
            zi.flag_bits = info.flag_bits
            zout.writestr(zi, files[info.filename], compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    with zipfile.ZipFile(args.output) as check:
        if check.testzip() is not None or [i.filename for i in check.infolist() if not i.is_dir()] != names:
            fail("final ZIP integrity/inventory mismatch")
    output_hash = hashlib.sha256(args.output.read_bytes()).hexdigest()
    args.output.with_suffix(args.output.suffix + ".sha256").write_text(output_hash + "  " + args.output.name + "\n", encoding="utf-8")
    receipt = {
        "result": "PASS",
        "release": TARGET,
        "baseline_release": SOURCE,
        "input_sha256": source_hash,
        "output_sha256": output_hash,
        "output_bytes": args.output.stat().st_size,
        "plugin_file_count": len(names),
        "php_file_count": sum(n.endswith(".php") for n in names),
        "plugin_root": ROOT,
        "changed_files": changed,
        "schema_contract": output_manifest["schema_contract"],
        "schema_changed": False,
        "live_business_data_changed": False,
        "cloud_ai": output_manifest["user_interface"]["management_intelligence"]["cloud_ai_boundary"],
        "owner_provided_health": {
            "recorded_date": "2026-10-24",
            "reported_wordpress": "7.1.3",
            "reported_php": "8.4.27",
            "reported_database": "MariaDB 11.4.13",
            "reported_schema_tables": "49/49",
            "reported_rest_routes": 104,
            "reported_supplemental_integrity": "CHECKS CLEAN",
            "reported_audit_chain": "PASS",
            "unresolved_compensation_records": 9,
            "full_deep_integrity_run_id": None,
            "evidence_origin": "Owner-provided System Health text; not directly queried by this build process"
        }
    }
    args.receipt.parent.mkdir(parents=True, exist_ok=True)
    args.receipt.write_text(json.dumps(receipt, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(json.dumps(receipt, indent=2, ensure_ascii=False))

if __name__ == "__main__":
    main()
