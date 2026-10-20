#!/usr/bin/env python3
"""Build Stockland ERP 16.1.92 from the exact checksum-locked 16.1.91 installer."""
from __future__ import annotations
import hashlib, json, sys, tempfile, zipfile
from pathlib import Path, PurePosixPath
ROOT=Path(__file__).resolve().parents[1]
LOCK=json.loads((ROOT/'ci/release-inputs-16.1.92.lock.json').read_text(encoding='utf-8'))
BASE=ROOT/LOCK['baseline_installer']['path']
OUT=ROOT/'STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip'
SHA=ROOT/(OUT.name+'.sha256')
QA=ROOT/'STOCKLAND_ERP_16.1.92_LOCAL_QA_RESULTS.txt'
AUDIT=ROOT/'STOCKLAND_ERP_16.1.92_DEEP_CODE_LEVEL_AUDIT.md'
TARGET='16.1.92'
sys.path.insert(0,str(ROOT/'ci'))
from patch_period_summary import patch_app_js
from patch_csv_import import patch_importer, patch_entrypoint

def hash_bytes(data: bytes) -> str: return hashlib.sha256(data).hexdigest()
def replace_once(data: bytes, old: str, new: str, label: str) -> bytes:
    text=data.decode('utf-8')
    if text.count(old)!=1: raise SystemExit(f'{label}: expected exactly one {old!r}, got {text.count(old)}')
    return text.replace(old,new,1).encode('utf-8')
def main():
    if not BASE.is_file(): raise SystemExit('Missing 16.1.91 canonical baseline ZIP')
    raw=BASE.read_bytes(); actual=hash_bytes(raw)
    if actual!=LOCK['baseline_installer']['sha256'] or len(raw)!=LOCK['baseline_installer']['size_bytes']:
        raise SystemExit('STOP: baseline installer SHA-256 or size does not match release-input lock')
    with zipfile.ZipFile(BASE) as z:
        if z.testzip() is not None: raise SystemExit('Baseline ZIP CRC failed')
        infos=[i for i in z.infolist() if not i.is_dir()]
        names=[i.filename for i in infos]
        if len(names)!=74 or len(set(names))!=74 or not all(PurePosixPath(n).parts[0]=='stockland-erp-app' and '..' not in PurePosixPath(n).parts for n in names):
            raise SystemExit('Baseline archive inventory/root/path check failed')
        files={n:z.read(n) for n in names}
    php_count=sum(n.endswith('.php') for n in names)
    if php_count!=47: raise SystemExit(f'Expected 47 baseline PHP files, got {php_count}')
    required={
      'stockland-erp-app/stockland-inventory.php',
      'stockland-erp-app/includes/class-stockland-frontend.php',
      'stockland-erp-app/assets/app.js',
      'stockland-erp-app/includes/rest/class-rest-import-v16.php',
      'stockland-erp-app/README.md',
      'stockland-erp-app/RELEASE_MANIFEST.json'}
    if not required.issubset(files): raise SystemExit('Required runtime source file is missing')
    p='stockland-erp-app/stockland-inventory.php'
    files[p]=replace_once(files[p],'* Version:           16.1.91','* Version:           16.1.92',p)
    files[p]=replace_once(files[p],"define( 'STOCKLAND_VERSION', '16.1.91' );","define( 'STOCKLAND_VERSION', '16.1.92' );",p)
    p='stockland-erp-app/includes/class-stockland-frontend.php'
    files[p]=replace_once(files[p],"const ROUTE_VERSION      = '16.1.91';","const ROUTE_VERSION      = '16.1.92';",p)
    p='stockland-erp-app/assets/app.js'
    files[p]=patch_app_js(files[p].decode('utf-8')).encode('utf-8')
    p='stockland-erp-app/includes/rest/class-rest-import-v16.php'
    files[p]=patch_importer(files[p].decode('utf-8')).encode('utf-8')
    p='stockland-erp-app/stockland-inventory.php'
    files[p]=patch_entrypoint(files[p].decode('utf-8')).encode('utf-8')
    new_csv='stockland-erp-app/includes/class-stockland-csv.php'
    if new_csv in files: raise SystemExit('CSV reader already exists in locked baseline')
    files[new_csv]=(ROOT/'ci/runtime/class-stockland-csv.php').read_bytes()
    mp='stockland-erp-app/RELEASE_MANIFEST.json'
    manifest=json.loads(files[mp].decode('utf-8'))
    manifest['release']=TARGET
    manifest['release_label']='16.1.92 — role-scoped daily, weekly and monthly CSV summaries'
    manifest['release_candidate_changes']=list(manifest.get('release_candidate_changes',[]))
    manifest['release_candidate_changes'].append('16.1.92: role/location-scoped daily, rolling-seven-day and month-to-date summary CSV exports; bounded CSV source support for Daily POS through existing staging, approval and locked transactional posting path.')
    manifest['corrective_release_16_1_92']={
      'baseline_release':'16.1.91','release_input_lock':'ci/release-inputs-16.1.92.lock.json',
      'changes':['Report presets are today, rolling seven days including today, and month-to-date.','Exports use existing authorized report response and server-enforced location scope.','Gross profit is emitted only for users whose existing bootstrap permission grants cost/profit visibility.','Per-staff details and current inventory snapshots are excluded from CSV summaries.','CSV quoting, UTF-8 BOM and formula-like text mitigation are included.','Daily POS CSV and XLSX rows use existing mapping, row validation, preview, staging, approval and release controller.','CSV bounds: 8 MB file, 15,050 records, 250 columns, 64 KB cell; UTF-8 and NUL checks.','Existing source hash, business-day close guard, SQL day lock, transaction and import-job FOR UPDATE lock remain unchanged.','Product Master remains XLSX-only; no schema, REST route, ledger posting, payroll formula or capability change.'],
      'production_acceptance':'PENDING — exact package CI is not live WordPress/MariaDB acceptance evidence.'}
    manifest.setdefault('quality',{})['release']=TARGET
    manifest['quality']['local_validation_status']='See exact 16.1.92 package build and QA receipts for source/package checks only.'
    manifest['quality']['target_host_acceptance']='PENDING — no authenticated live WordPress/MariaDB run was performed by the build.'
    manifest['quality']['production_certification']='PENDING — live role-scoped acceptance, integrity/ledger reconciliation and verified backup restore/rollback have not been performed by CI.'
    manifest['quality']['no_unverified_live_acceptance_claims']=True
    manifest['release_acceptance']={'source_package_gate':'Exact 16.1.92 source/package gate must pass; not a substitute for live acceptance.','target_host':'PENDING — no authenticated live production execution performed.','production_deployment':'NOT PERFORMED by build job.','note':'Summary CSV is read-only. Daily POS CSV/XLSX goes through the current staged/locked posting workflow. Live integrity, role acceptance, restore and rollback require independent evidence.'}
    files[mp]=(json.dumps(manifest,ensure_ascii=False,indent=2)+'\n').encode('utf-8')
    rp='stockland-erp-app/README.md'; readme=files[rp].decode('utf-8')
    if not readme.startswith('# Stockland Ethiopia ERP 16.1.91'): raise SystemExit('README release marker is not the locked baseline')
    readme=readme.replace('# Stockland Ethiopia ERP 16.1.91','# Stockland Ethiopia ERP 16.1.92',1)
    overview='**Stockland ERP** is a role-based, responsive operations and finance platform for Stockland Ethiopia, supporting the central warehouse, branches, Product Master, retail operations, procurement, finance, reporting and payroll.'
    if overview not in readme: raise SystemExit('Expected README overview is missing')
    feature='''## Daily, weekly and monthly summary CSV — 16.1.92

- Reports & Analytics adds today, rolling-seven-day and month-to-date presets and a CSV download using the existing authorized reporting response.
- Branch/location scope stays enforced by the server. Gross profit is included only where the current permission allows it; staff-level details and current inventory snapshots are not exported.
- CSV fields are quoted, UTF-8 spreadsheet compatibility is included, and formula-like strings are prefixed to reduce CSV formula execution risk.
- Daily POS accepts transaction-level CSV or XLSX through the existing mapping, validation, preview, staged approval, duplicate-source checks, business-day-close guard, day lock and transaction/row-lock path. Uploading does not post sales. Product Master remains XLSX-only.
- Daily/weekly/monthly report totals are read-only summaries and are never imported as sales.
- The release-input lock pins the exact baseline package SHA-256, byte size, archive inventory and 49-table contract. The npm and Composer locks are CI-only; runtime dependencies do not change.

**Acceptance boundary:** source/package checks do not certify the live ERP. Live role tests, ledger/deep integrity, verified backup restore and rollback remain separate acceptance steps.

'''
    readme=readme.replace(overview,overview+'\n\n'+feature.rstrip(),1)
    files[rp]=readme.encode('utf-8')
    with tempfile.TemporaryDirectory(prefix='stockland-1692-') as td:
        staged=Path(td)
        for name,data in files.items():
            dest=staged/PurePosixPath(name); dest.parent.mkdir(parents=True,exist_ok=True); dest.write_bytes(data)
        with zipfile.ZipFile(OUT,'w',compression=zipfile.ZIP_DEFLATED,compresslevel=9) as out:
            for info in infos:
                n=info.filename; zi=zipfile.ZipInfo(n,date_time=info.date_time); zi.compress_type=zipfile.ZIP_DEFLATED; zi.external_attr=info.external_attr; zi.create_system=info.create_system; zi.flag_bits=info.flag_bits; out.writestr(zi,files[n],compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
            zi=zipfile.ZipInfo(new_csv,date_time=(2026,1,1,0,0,0)); zi.compress_type=zipfile.ZIP_DEFLATED; zi.external_attr=(0o100644 & 0xffff)<<16; zi.create_system=3; out.writestr(zi,files[new_csv],compress_type=zipfile.ZIP_DEFLATED,compresslevel=9)
    output_hash=hashlib.sha256(OUT.read_bytes()).hexdigest(); SHA.write_text(f'{output_hash}  {OUT.name}\n',encoding='utf-8')
    QA.write_text(f'''Stockland ERP 16.1.92 exact-package build receipt
Baseline: {BASE.name}
Baseline SHA-256: {actual}
Installer: {OUT.name}
Installer bytes: {OUT.stat().st_size}
Installer SHA-256: {output_hash}
Package files: {len(infos)+1}
PHP files: {php_count+1}
Schema contract unchanged: 29 core + 16 accounting/procurement + 4 HR/payroll = 49
Runtime dependency additions: none
Live WordPress/MariaDB deployment: NOT PERFORMED
''',encoding='utf-8')
    AUDIT.write_text(f'''# Stockland Ethiopia ERP 16.1.92 — Release Audit

- Installer: {OUT.name}
- Size: {OUT.stat().st_size:,} bytes
- SHA-256: {output_hash}
- Exact base: {BASE.name}, SHA-256 {actual}
- Package: {len(infos)+1} files under the single stockland-erp-app root, {php_count+1} PHP files.
- Baseline/schema lock: ci/release-inputs-16.1.92.lock.json. CI-only PostCSS npm and WPCS Composer lockfiles are separately generated once and shared by release jobs.

## Changes and preservation

- Adds daily/today, rolling-seven-day, and month-to-date summary CSV export to Reports & Analytics using the existing role/location-scoped response. Staff detail and live stock snapshots are omitted; gross profit is gated by the existing cost permission.
- Adds bounded streaming CSV reading only to Daily POS. CSV and XLSX both use the existing column mapping, validation, preview, staging/approval, duplicate/source hash and locked posting pipeline. Product Master stays XLSX-only.
- CSV limits are 8 MB file, 15,050 records, 250 columns and 64 KB cells, with UTF-8 and NUL validation. No new business tables, REST routes, role capabilities, external providers, cron dependency, database trigger, ledger posting, or payroll calculation changes.
- Existing SQL day lock, transaction and import-job FOR UPDATE remain intact.

## Verification and production boundary

Exact-version CI must pass PHP 8.3/8.4, package checksum/inventory, JavaScript, CSV tests, payroll/System Health, existing notification/payroll preview, CSS parity, WPCS and Semgrep. Passing CI does not prove live-site acceptance.

The live 16.1.91 health snapshot supplied by the owner showed 49/49 schema and 103 REST routes passing, with payroll compensation classification as the only failed production gate due to seven active employee records missing compensation records. This candidate does not modify or resolve those records. No live WordPress/MariaDB install or mutation, live role acceptance, backup restoration or rollback was performed by the builder.
''',encoding='utf-8')
    print(f'Built {OUT.name}: {OUT.stat().st_size} bytes, SHA-256 {output_hash}')
if __name__=='__main__': main()
