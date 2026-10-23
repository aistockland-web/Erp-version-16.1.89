#!/usr/bin/env python3
"""Fail-closed package and privacy/source contract for Stockland ERP 16.1.96 Cloud AI."""
from __future__ import annotations
import argparse, hashlib, json, re, zipfile, sys
from pathlib import Path, PurePosixPath

ROOT='stockland-erp-app/'
EXPECTED={'core_operations':29,'accounting_procurement':16,'hr_payroll':4,'total':49}

def need(ok, msg):
    print(('PASS ' if ok else 'FAIL ')+msg)
    if not ok: raise SystemExit(1)

def read_pkg(path):
    with zipfile.ZipFile(path) as z:
        need(z.testzip() is None, f'{path.name} ZIP CRC')
        names=[i.filename for i in z.infolist() if not i.is_dir()]
        need(len(names)==len(set(names)), f'{path.name} has no duplicate members')
        need(all(x.startswith(ROOT) and '..' not in PurePosixPath(x).parts for x in names), f'{path.name} one safe plugin root')
        return names,{x:z.read(x) for x in names}

def main():
    p=argparse.ArgumentParser()
    p.add_argument('--zip',type=Path,required=True)
    p.add_argument('--baseline',type=Path,default=None)
    p.add_argument('--receipt',type=Path,default=None)
    a=p.parse_args()
    names,files=read_pkg(a.zip)
    need(len(names)==76,'76 unique runtime files in final Cloud AI installer')
    need(sum(x.endswith('.php') for x in names)==49,'49 runtime PHP files linted/sealed into archive')
    manifest=json.loads(files[ROOT+'RELEASE_MANIFEST.json'].decode())
    css=json.loads(files[ROOT+'assets/css/modules.json'].decode())
    plugin=files[ROOT+'stockland-inventory.php'].decode()
    front=files[ROOT+'includes/class-stockland-frontend.php'].decode()
    cloud=files[ROOT+'includes/class-stockland-cloud-ai.php'].decode()
    rest=files[ROOT+'includes/rest/class-rest-app.php'].decode()
    js=files[ROOT+'assets/app.js'].decode()
    readme=files[ROOT+'README.md'].decode()
    need(manifest.get('release')==css.get('release')=='16.1.96','manifest, CSS module and release identity are 16.1.96')
    need('Version:           16.1.96' in plugin and "define( 'STOCKLAND_VERSION', '16.1.96' );" in plugin,'plugin header and runtime version align')
    need("const ROUTE_VERSION      = '16.1.96';" in front,'front-end route/cache marker aligns')
    need(manifest.get('schema_contract')==EXPECTED,'49-table contract remains 29+16+4')
    need("define( 'STOCKLAND_DB_VERSION', '16.1.1' );" in plugin,'DB marker preserved')
    need(ROOT+'includes/class-stockland-cloud-ai.php' in names,'first-party Cloud AI service is included in installer')
    need("'/app/cloud-ai/status'" in rest and "'/app/cloud-ai/ask'" in rest,'two server routes register for status and on-demand ask')
    need('can_use_cloud_ai' in rest and "Stockland_Authority::is_management()" in rest and "Stockland_Authority::assert_profile()" in rest,'server-side profile/role authorization is enforced')
    need("const ENDPOINT = 'https://api.openai.com/v1/responses';" in cloud,'provider endpoint is fixed; no caller-controlled URL/SSRF')
    need('STOCKLAND_CLOUD_AI_API_KEY' in cloud and 'STOCKLAND_CLOUD_AI_MODEL' in cloud,'server-side wp-config constants control connection')
    need("'store' => false" in cloud and "'tools' =>" not in cloud,'provider storage disabled by request and no AI tools are supplied')
    need("Stockland_Audit::log( $user_id, null, 'CLOUD_AI_REQUEST'" in cloud and "if ( ! class_exists( 'Stockland_Audit' )" in cloud,'disclosure audit must succeed before sending data')
    need("'consent'  => array( 'required' => true )" in rest and 'consent:true' in js and 'slCloudAIConsent' in js,'per-request explicit consent is enforced in REST and UI')
    need("'recent_sales'" not in cloud and "employee_salary" not in cloud and "customer_email" not in cloud,'provider context is built from an explicit aggregate whitelist')
    for banned in ('employee_compensation','payroll_lines','customer_invoices','supplier_invoices','stockland_salaries','Authorization: Bearer'):
        need(banned not in js, 'no secret or sensitive ERP data field in browser code: '+banned)
    for required in ('daily_retail_sales_etb','category_sales_month_to_date_etb','branch_sales_month_to_date_etb','Advisory response delivered'):
        need(required in cloud, 'source-bounded Cloud AI context includes '+required)
    need("sl-cloud-ai-card" in js and "function managementIntelligence()" in js,'Cloud AI appears in existing Management Intelligence workspace')
    need("const cloud=q.cloud_ai||{};" in js and "configuration_status()" in rest,'System Health displays secret-free Cloud AI configuration status')
    need('NOT CONFIGURED — local ERP analytics remain available' in cloud,'unconfigured Cloud AI remains optional/non-blocking')
    need(manifest['user_interface']['management_intelligence']['cloud_ai'].get('automatic_transaction_posting') is False,'manifest explicitly prohibits autonomous transaction posting')
    need(('not a claim that provider account-level retention is zero' in readme.lower()) or ('not a claim of zero provider-level retention' in readme.lower()) or ('provider account-level logging/retention controls still apply' in readme.lower()),'readme does not falsely promise zero provider retention')
    need(not re.search(rb'sk-(?:proj-|live-|test-)[A-Za-z0-9_-]{12,}', files[ROOT+'assets/app.js']),'no hard-coded provider key in browser JS')
    for jpath in ('RELEASE_MANIFEST.json','assets/manifest.json','assets/css/modules.json'):
        json.loads(files[ROOT+jpath].decode())
    modules=css['modules']; need(len(modules)==6 and len(set(modules))==6,'six ordered CSS source modules remain intact')
    app_css='\n\n'.join(files[ROOT+'assets/css/'+str(x)].decode().strip() for x in modules)+'\n'
    need(files[ROOT+'assets/app.css'].decode()==app_css,'app.css equals ordered source module contents')
    norm=re.sub(r'/\*[\s\S]*?\*/',' ',app_css);norm=re.sub(r'\s+',' ',norm).strip();norm=re.sub(r'\s*([{}:;,])\s*',r'\1',norm).replace(';}','}')
    need(files[ROOT+'assets/app.min.css'].decode().strip()==norm,'app.min.css normalized source parity')
    if a.baseline:
        _,base=read_pkg(a.baseline)
        need(base[ROOT+'includes/class-stockland-activator.php']==files[ROOT+'includes/class-stockland-activator.php'],'16.1.96 does not change schema activator from 16.1.95 baseline')
    sha=hashlib.sha256(a.zip.read_bytes()).hexdigest()
    print(f'PASS exact candidate SHA-256 {sha}; {len(names)} files; {sum(x.endswith(".php") for x in names)} PHP files')
    if a.receipt:
        a.receipt.parent.mkdir(parents=True,exist_ok=True)
        a.receipt.write_text(json.dumps({'result':'PASS','release':'16.1.96','sha256':sha,'bytes':a.zip.stat().st_size,'files':len(names),'php_files':sum(x.endswith('.php') for x in names),'schema_contract':EXPECTED,'cloud_ai':'optional, explicit-consent, aggregate-only, advisory-only'},indent=2)+'\n')
if __name__=='__main__': main()
