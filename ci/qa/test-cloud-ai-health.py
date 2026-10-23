#!/usr/bin/env python3
"""Source-level health screen contract: optional Cloud AI is visible but not a readiness dependency."""
import json,sys,zipfile
from pathlib import Path
zpath=Path(sys.argv[1])
with zipfile.ZipFile(zpath) as z:
 f={x.filename:z.read(x.filename).decode('utf-8',errors='replace') for x in z.infolist() if not x.is_dir() and x.filename.startswith('stockland-erp-app/')}
r=f['stockland-erp-app/includes/rest/class-rest-app.php'];j=f['stockland-erp-app/assets/app.js'];m=json.loads(f['stockland-erp-app/RELEASE_MANIFEST.json'])
checks=[
 ('quick and deep Health return a secret-free cloud_ai diagnostic',r.count("$diagnostics['cloud_ai'] = class_exists( 'Stockland_Cloud_AI' ) ? Stockland_Cloud_AI::configuration_status()")>=2),
 ('Health screen displays provider configuration and retention policy',"const cloud=q.cloud_ai||{};" in j and "cloud.retention||'No provider connectivity test was run by System Health.'" in j),
 ('cloud AI configuration is not added to live_ready mandatory predicate',"$checks['live_ready'] = (" in r and "$checks['cloud_ai']" not in r),
 ('supplemental scan excludes only the identified optional API client from mandatory-runtime scan',"'class-stockland-cloud-ai.php' !== basename( $source_file )" in r),
 ('manifest describes cloud AI as optional and consent-based','optional and disabled until wp-config is configured' in m['user_interface']['management_intelligence']['cloud_ai']['integration'] and m['user_interface']['management_intelligence']['cloud_ai']['explicit_consent_required']),
]
for name,ok in checks: print(('PASS ' if ok else 'FAIL ')+name)
if not all(x[1] for x in checks):sys.exit(1)
