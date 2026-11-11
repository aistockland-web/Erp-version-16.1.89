#!/usr/bin/env python3
"""Compare PHP-only WPCS security/database findings between 16.2.13 and 16.2.15."""
import argparse
import json
from pathlib import Path

def load(path):
    data = json.loads(Path(path).read_text(encoding='utf-8'))
    if not isinstance(data.get('files'), dict) or not isinstance(data.get('totals'), dict):
        raise ValueError('Invalid PHPCS JSON report: ' + str(path))
    findings = set()
    for file_path, info in data['files'].items():
        normalized = file_path.replace('\\\\', '/')
        marker = '/stockland-erp-app/'
        relative = normalized.split(marker, 1)[1] if marker in normalized else Path(normalized).name
        for item in info.get('messages', []):
            rule = str(item.get('source', ''))
            if rule.startswith(('WordPress.Security.', 'WordPress.DB.')):
                findings.add((rule, relative, str(item.get('message', ''))))
    return data['totals'], data['files'], findings

def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--baseline', required=True)
    parser.add_argument('--candidate', required=True)
    args = parser.parse_args()
    base_totals, base_files, base_findings = load(args.baseline)
    next_totals, next_files, next_findings = load(args.candidate)
    new_findings = sorted(next_findings - base_findings)
    print('16.2.13 baseline PHP WPCS: errors={errors}, warnings={warnings}, fixable={fixable}, files={files}, security/database={security}'.format(
        errors=base_totals.get('errors', 0), warnings=base_totals.get('warnings', 0),
        fixable=base_totals.get('fixable', 0), files=len(base_files), security=len(base_findings)))
    print('16.2.15 candidate PHP WPCS: errors={errors}, warnings={warnings}, fixable={fixable}, files={files}, security/database={security}'.format(
        errors=next_totals.get('errors', 0), warnings=next_totals.get('warnings', 0),
        fixable=next_totals.get('fixable', 0), files=len(next_files), security=len(next_findings)))
    print('New .15 WordPress.Security.* or WordPress.DB.* findings over .13: ' + str(len(new_findings)))
    for rule, path, message in new_findings:
        print('NEW ' + rule + ' ' + path + ': ' + message)
    print('Full PHP-only WPCS JSON reports remain artifacts. Existing .13 findings are not declared fixed or production-approved.')
    if new_findings:
        raise SystemExit(1)
    print('PASS: no new WPCS security/database findings relative to 16.2.13.')

if __name__ == '__main__':
    main()
