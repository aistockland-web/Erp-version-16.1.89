#!/usr/bin/env python3
"""Contract tests for source-level direct legacy HR table trace."""
import csv, json, sys
from pathlib import Path
root=Path(sys.argv[1] if len(sys.argv)>1 else 'build/hr-replacement-baseline')
report=json.loads((root/'hr_legacy_direct_table_calls.json').read_text(encoding='utf-8'))
with (root/'hr_legacy_direct_table_calls.csv').open(encoding='utf-8-sig',newline='') as f:
    rows=list(csv.DictReader(f))
def req(ok,msg):
    if not ok: raise SystemExit('FAIL: '+msg)
req(report['baseline_sha256']=='494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625','not the locked baseline')
req(report['direct_stockland_db_table_helper_calls']=={'employees':14,'employee_compensation':5,'payroll_periods':17,'payroll_lines':13},'direct helper call inventory changed')
req(len(rows)==49 and report['total_direct_calls']==49,'expected 49 direct table-helper calls')
req(all(row['method_guess'] and row['source_file'] and row['line'] and row['review_required'].startswith('YES') for row in rows),'trace rows must retain source location and manual-review warning')
req({r['legacy_table'] for r in rows}=={'employees','employee_compensation','payroll_periods','payroll_lines'},'all four legacy sources must be represented')
print('PASS legacy HR direct table-call trace: 49 exact source locations; no runtime/database mutation')
