#!/usr/bin/env python3
"""Exact ZIP, source-preservation and dependency-lock contract for ERP 16.1.92."""
import hashlib, json, sys, zipfile
from pathlib import Path, PurePosixPath
ROOT=Path(__file__).resolve().parents[2]
LOCK=json.loads((ROOT/'ci/release-inputs-16.1.92.lock.json').read_text(encoding='utf-8'))
BASE=ROOT/LOCK['baseline_installer']['path']
ZIP=ROOT/'STOCKLAND_ERP_16.1.92_CANONICAL_WORDPRESS_UPDATE.zip'
SHA=ROOT/(ZIP.name+'.sha256')
TARGET='16.1.92'
ALLOWED={
'stockland-erp-app/stockland-inventory.php',
'stockland-erp-app/includes/class-stockland-frontend.php',
'stockland-erp-app/assets/app.js',
'stockland-erp-app/includes/rest/class-rest-import-v16.php',
'stockland-erp-app/README.md',
'stockland-erp-app/RELEASE_MANIFEST.json'}
def require(ok, message):
    if not ok: raise SystemExit('FAIL: '+message)
def digest(raw): return hashlib.sha256(raw).hexdigest()
def main():
    require(BASE.is_file() and ZIP.is_file() and SHA.is_file(),'baseline, candidate ZIP and checksum receipt must exist')
    require(digest(BASE.read_bytes())==LOCK['baseline_installer']['sha256'],'base installer SHA-256 does not match release-input lock')
    require(BASE.stat().st_size==LOCK['baseline_installer']['size_bytes'],'base installer size does not match release-input lock')
    raw=ZIP.read_bytes()
    require(SHA.read_text(encoding='utf-8').split()[0]==digest(raw),'candidate ZIP SHA-256 receipt mismatch')
    with zipfile.ZipFile(BASE) as bz, zipfile.ZipFile(ZIP) as z:
        require(bz.testzip() is None and z.testzip() is None,'ZIP CRC check failed')
        bn=[x.filename for x in bz.infolist() if not x.is_dir()]
        nn=[x.filename for x in z.infolist() if not x.is_dir()]
        require(len(bn)==74 and len(set(bn))==74,'expected unique 74-file baseline package')
        require(nn[:-1]==bn,'existing package member order changed')
        require(nn[-1]=='stockland-erp-app/includes/class-stockland-csv.php','only new runtime member must be CSV reader')
        require(len(nn)==75 and len(set(nn))==75,'expected 75 unique candidate package files')
        require(all(PurePosixPath(p).parts[0]=='stockland-erp-app' and '..' not in PurePosixPath(p).parts for p in nn),'unexpected package root or unsafe path')
        names={p:z.read(p) for p in nn}
        before={p:bz.read(p) for p in bn}
    require(sum(p.endswith('.php') for p in names)==48,'expected 48 packaged PHP runtime files')
    require({p for p in before if before[p]!=names[p]}==ALLOWED,'changes outside the narrow allow-list: '+str(sorted({p for p in before if before[p]!=names[p]} ^ ALLOWED)))
    require(set(names)-set(before)=={'stockland-erp-app/includes/class-stockland-csv.php'},'candidate added unexpected package member')
    entry=names['stockland-erp-app/stockland-inventory.php'].decode()
    front=names['stockland-erp-app/includes/class-stockland-frontend.php'].decode()
    js=names['stockland-erp-app/assets/app.js'].decode()
    importer=names['stockland-erp-app/includes/rest/class-rest-import-v16.php'].decode()
    csv=names['stockland-erp-app/includes/class-stockland-csv.php'].decode()
    readme=names['stockland-erp-app/README.md'].decode()
    manifest=json.loads(names['stockland-erp-app/RELEASE_MANIFEST.json'].decode())
    require('* Version:           16.1.92' in entry and "define( 'STOCKLAND_VERSION', '16.1.92' );" in entry,'plugin version is inconsistent')
    require("const ROUTE_VERSION      = '16.1.92';" in front,'frontend cache/route marker is inconsistent')
    require('function buildPeriodSummaryCsv(' in js and 'function reportPresetRange(' in js,'report CSV or period presets missing')
    require('data-report-preset="daily"' in js and 'data-report-preset="weekly"' in js and 'data-report-preset="monthly"' in js,'all three report preset buttons are required')
    require('downloadOpsReportCsv' in js,'CSV download action is missing')
    require('Daily POS CSV or Excel (.csv / .xlsx)' in js and 'accept=".csv,.xlsx' in js,'Daily POS UI must accept CSV and XLSX')
    require("private function uploaded_file( $key = 'file', $allow_csv = false )" in importer and "$this->uploaded_file( 'file', true )" in importer,'Daily POS upload must explicitly opt into CSV')
    require("$file['extension'] ?? ''" in importer and 'new Stockland_CSV' in importer,'Daily POS parser dispatch is missing')
    require("new Stockland_XLSX( $file['path'] )" in importer and importer.count("$this->uploaded_file( 'file' )")>=1,'Product Master must remain XLSX-only')
    require('MAX_FILE_BYTES' in csv and 'MAX_CELLS_PER_ROW' in csv and 'MAX_CELL_BYTES' in csv and 'MAX_PHYSICAL_ROW_NODES' in csv,'CSV resource caps are missing')
    require("preg_match( '//u'" in csv and '\\0' in csv,'UTF-8 validation and NUL rejection are required')
    require('schema contract' in readme.lower(),'README must explain schema preservation')
    schema=manifest['schema_contract']
    require(manifest['release']==TARGET and manifest['quality']['release']==TARGET,'manifest release markers disagree')
    require((schema.get('core_operations'),schema.get('accounting_procurement'),schema.get('hr_payroll'),schema.get('total'))==(29,16,4,49),'49-table contract changed')
    require(schema.get('new_business_table') is False,'new business table is not allowed')
    require(manifest['runtime_rules']['no_cron_dependency'] is True and manifest['runtime_rules']['no_database_triggers'] is True,'runtime scheduler/trigger policy changed')
    require(manifest['corrective_release_16_1_92']['production_acceptance'].startswith('PENDING'),'live acceptance must not be claimed')
    require(any('Daily POS CSV or XLSX' in item for item in manifest['corrective_release_16_1_92']['changes']),'manifest must document CSV input')
    package=json.loads((ROOT/'ci/cssqa/package.json').read_text(encoding='utf-8'))
    npmlock=json.loads((ROOT/'ci/cssqa/package-lock.json').read_text(encoding='utf-8'))
    require(npmlock.get('lockfileVersion')==3 and npmlock.get('name')==package.get('name'),'npm lock version/name is invalid')
    require(package.get('devDependencies',{}).get('postcss')=='8.5.31' and npmlock.get('packages',{}).get('node_modules/postcss',{}).get('version')=='8.5.31','PostCSS is not pinned to the tested exact version')
    composer=json.loads((ROOT/'ci/phpcs/composer.json').read_text(encoding='utf-8'))
    lock=json.loads((ROOT/'ci/phpcs/composer.lock').read_text(encoding='utf-8'))
    locked={x.get('name'):x.get('version') for x in lock.get('packages-dev',[])}
    require(lock.get('name')==composer.get('name') and bool(lock.get('content-hash')),'Composer lock does not match its manifest')
    require('wp-coding-standards/wpcs' in locked,'Composer lock must pin WPCS')
    print('PASS: SHA-locked base; ZIP CRC and member order; 75 files / 48 PHP; only the allow-listed app patches and one CSV reader.')
    print('PASS: daily, weekly and monthly read-only report CSV; safe Daily POS CSV staged through the existing locked posting workflow; Product Master remains XLSX-only.')
    print('PASS: 29 + 16 + 4 = 49 schema unchanged; npm and Composer QA lockfiles validated; no live acceptance claim.')
if __name__=='__main__': main()
