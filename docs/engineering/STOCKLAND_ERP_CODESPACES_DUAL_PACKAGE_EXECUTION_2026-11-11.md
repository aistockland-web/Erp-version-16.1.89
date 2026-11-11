# Stockland ERP — Codespaces dual-package execution plan

Date: 11 November 2026
Repository base: main at 1c3b743296b7a1f4e3b8a0b481e6883b5fed4154
Working branch: work/codespaces-end-to-end-2026-11-11
Notion execution authority: https://app.notion.com/p/4106db0f767e8119a322ff39cf928763
Notion baseline decision: https://app.notion.com/p/4136db0f767e81ee87cec44eac9c4340
Notion release gates: https://app.notion.com/p/3fc6db0f767e81f2a3bbe644048054f9

## 1. Decision and scope

Preserve 16.2.13 as the operational upgrade baseline and verify 16.2.15 as the selected cumulative update candidate. Both ZIPs and their sidecars are already committed on main. This change does not duplicate, rename or modify either archive; it adds reproducible Codespaces and CI verification.

References:
- 1c3b743: 16.2.13 intake receipt and source manifest.
- 6dd5263: 16.2.15 candidate ZIP and SHA-256 sidecar.
- PR #77: Owner's 16.2.13 baseline correction.

The .15 sidecar records 8c46bf052dd5d9553964909b6d56268b2c195e8a056f74bfb423ae9d21d142df; it is not called freshly verified until CI recomputes it from the ZIP. The .13 historical intake SHA is 74e3205d0b42151997dc356c29b09204688accee817c17a013a70cf90be04c13; CI rehashes that archive too.

## 2. Environment design

- PHP 8.3 workspace, Node 22, Python 3.12, Composer, WP-CLI and SQL clients.
- Baseline sandbox: WordPress 7.1.4 / PHP 8.3 + MariaDB 10.11; clean install and .13 activation.
- Upgrade sandbox: separate WordPress 7.1.4 / PHP 8.3 + MySQL 8.0.46; clean install .13, then replace only plugin files with verified .15 while retaining the disposable DB.
- Ports 8080/8081 are private. Database ports are internal. Databases/uploads are separate.
- Generated administrator credentials stay in ignored runtime storage. No production or personal data.

## 3. Archive integrity

The Python verifier checks archive/sidecar presence, committed sidecar digest, actual SHA-256, ZIP CRC, duplicate paths, safe path segments, one stockland-erp-app root, no symlinks, bounded uncompressed size and the root WordPress plugin header. It refuses extraction when any check fails and emits machine-readable size/count/hash evidence.

The .13 ZIP has a Git suffix-disambiguated filename while its historic sidecar has the original unsuffixed name. Digest is authoritative; filename-string mismatch is documented, not silently edited.

## 4. CI sequence

- Verify actual .13/.15 ZIP bytes, sidecars, CRC and path-safety.
- Lint all PHP files on PHP 8.3 and 8.4.
- Check all JS, JSON, CSS source-module parity and PostCSS parsing.
- Run isolated .13 WordPress/MariaDB installation/activation.
- Run isolated .13-to-.15 candidate update/boot on WordPress/MySQL.
- Run locked PHPCS/WPCS rules on both exact packages without suppressing diagnostics.

The runtime smoke is limited to package integrity, site install, plugin activation, update boot and REST namespace visibility. It is not full POS/inventory/finance/HR transaction certification.

## 5. Remaining acceptance gates

This environment does not close exact .13/.15 business-lineage review; live host/source/schema parity; schema contract and approved ERD; MySQL/MariaDB FK/concurrency/idempotency regressions; POS, stock, warehouse, counts and daily close; procurement/AP/AR/GL/PLEDGER; HR lifecycle, encrypted private vault, payroll and two periods; qualified Ethiopian statutory/legal review; 24 draft SOP approvals; complete Semgrep/CodeQL/secret/dependency/license/SBOM; browser/device PWA; backup/restore/rollback; Owner/Operations/Finance/HR acceptance.

## 6. Data and rollback policy

No production system is accessed. Baseline and upgrade DBs are separate and synthetic. Reset only the named Codespaces test resources. Never point these services at an external or production DB.

## 7. Status at plan creation

This plan is not a test pass. After commit/PR, add exact branch/head, workflow IDs, results, recomputed package hashes and open findings. Update the linked Notion record only after the Notion API confirms the write.
