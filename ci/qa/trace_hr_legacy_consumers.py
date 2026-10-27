#!/usr/bin/env python3
"""Trace direct legacy HR table helper call sites in the checksum-locked ERP ZIP.

This is a source navigation aid; it is not a runtime data-flow proof or an authorization test.
"""
from __future__ import annotations
import argparse, csv, hashlib, json, re, zipfile
from pathlib import Path, PurePosixPath

BASE_SHA256 = '494e777247508d009dfdb216c9607bb1bc96717197e94a0e8ad3459b7cc81625'
BASE_SIZE = 1_102_405
ROOT = 'stockland-erp-app/'
TABLES = ('employees', 'employee_compensation', 'payroll_periods', 'payroll_lines')
EXPECTED_DIRECT_CALLS = {'employees': 14, 'employee_compensation': 5, 'payroll_periods': 17, 'payroll_lines': 13}

def sha256(raw: bytes) -> str:
    return hashlib.sha256(raw).hexdigest()

def method_at(source: str, pos: int) -> str:
    methods = list(re.finditer(r'(?m)^\s*(?:public|private|protected)\s+(?:static\s+)?function\s+([A-Za-z_]\w*)', source[:pos]))
    if not methods:
        return '(class scope / method not resolved)'
    return methods[-1].group(1)

def classify(source: str, start: int, end: int) -> str:
    snippet = source[max(0, start - 260):min(len(source), end + 420)]
    checks = [
        ('insert', r'insert_record\s*\('),
        ('update', r'update_record\s*\('),
        ('delete', r'\bDELETE\s+FROM\b|delete_record\s*\('),
        ('select', r'\bSELECT\b|read_(?:row|results|var)\s*\('),
    ]
    found = [label for label, pattern in checks if re.search(pattern, snippet, re.I)]
    if len(found) == 1:
        return found[0]
    if found:
        return 'mixed_context:' + '+'.join(found)
    return 'table_handle_or_unclassified'

def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument('--package', required=True)
    parser.add_argument('--out-dir', required=True)
    args = parser.parse_args()
    package, out = Path(args.package), Path(args.out_dir)
    if not package.is_file():
        raise SystemExit('FAIL: baseline package missing')
    raw = package.read_bytes()
    if len(raw) != BASE_SIZE or sha256(raw) != BASE_SHA256:
        raise SystemExit('FAIL: source trace must use the exact locked 16.1.95 baseline')
    with zipfile.ZipFile(package) as archive:
        if archive.testzip() is not None:
            raise SystemExit('FAIL: ZIP CRC error')
        names = [item.filename for item in archive.infolist() if not item.is_dir()]
        if len(names) != 75 or len(names) != len(set(names)) or any(PurePosixPath(n).parts[0] != ROOT.rstrip('/') for n in names):
            raise SystemExit('FAIL: package inventory/root differs from lock')
        php = {n: archive.read(n).decode('utf-8', 'replace') for n in names if n.endswith('.php')}
    rows = []
    counts = {key: 0 for key in TABLES}
    for path, source in sorted(php.items()):
        for key in TABLES:
            pattern = re.compile(r"Stockland_DB::table\s*\(\s*['\"](?:stock_)?" + re.escape(key) + r"['\"]\s*\)", re.I)
            for match in pattern.finditer(source):
                line = source.count('\n', 0, match.start()) + 1
                line_start = source.rfind('\n', 0, match.start()) + 1
                line_end = source.find('\n', match.end())
                if line_end < 0:
                    line_end = len(source)
                counts[key] += 1
                rows.append({
                    'legacy_table': key,
                    'source_file': path,
                    'line': line,
                    'method_guess': method_at(source, match.start()),
                    'context_classification': classify(source, match.start(), match.end()),
                    'source_line_excerpt': re.sub(r'\s+', ' ', source[line_start:line_end]).strip()[:240],
                    'review_required': 'YES — verify complete method, query/write, permissions and side effects manually',
                })
    if counts != EXPECTED_DIRECT_CALLS:
        raise SystemExit('FAIL: direct table-helper call counts changed; review baseline lock and scanner. Found ' + json.dumps(counts, sort_keys=True))
    out.mkdir(parents=True, exist_ok=True)
    csv_path = out / 'hr_legacy_direct_table_calls.csv'
    with csv_path.open('w', encoding='utf-8-sig', newline='') as handle:
        fields = ['legacy_table', 'source_file', 'line', 'method_guess', 'context_classification', 'source_line_excerpt', 'review_required']
        writer = csv.DictWriter(handle, fieldnames=fields)
        writer.writeheader(); writer.writerows(rows)
    report = {
        'baseline_sha256': sha256(raw), 'baseline_size_bytes': len(raw), 'package_files': len(names),
        'php_source_files': len(php), 'direct_stockland_db_table_helper_calls': counts,
        'total_direct_calls': len(rows), 'source_trace': 'line/function guesses and small-context operation labels are navigation hints, not execution proof',
        'migration_rule': 'Do not retire any legacy table until each consumer is manually traced, migrated/adapted, regression-tested and covered by cutover evidence.',
        'csv': csv_path.name,
    }
    json_path = out / 'hr_legacy_direct_table_calls.json'
    json_path.write_text(json.dumps(report, indent=2) + '\n', encoding='utf-8')
    print(json.dumps(report, indent=2))

if __name__ == '__main__':
    main()
