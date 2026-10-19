# Stockland Ethiopia ERP 16.1.89 — Header, Theme and Install UX Deep Audit

**Disposition:** Local package/regression checks passed. Live production acceptance and exact-version GitHub CI remain pending; this release was not deployed.

## Release artifact

- Installer: `STOCKLAND_ERP_16.1.89_CANONICAL_WORDPRESS_UPDATE.zip`
- Size: 1,104,097 bytes
- SHA-256: `4b8a5fe5648ddf062953c671fe572cb62235757d3a092da6b23116aacae392c3`
- Baseline installer: `STOCKLAND_ERP_16.1.88_STAGING_CANDIDATE_WORDPRESS_UPDATE.zip`
- Baseline SHA-256: `e7ce6ecb9ffdca987409dc07c6d15cfc0e150a988a84eb9d55d628a0f09c01a8`
- ZIP integrity: all 74 members pass; single expected root `stockland-erp-app/`.

## What was changed

- Header surface/border/top accent inherit the selected theme tokens.
- Global search focus, notification/header action colors and hover states, profile menu border/shadow/hover/focus, and mobile controls derive from the same selected palette.
- The active theme updates `meta[name=theme-color]` for the browser chrome when switching palettes.
- The four theme IDs and their definitions remain exact: `sapphire`, `emerald`, `violet`, `copper`. The existing `stockland_erp_theme` local-storage preference key is unchanged.
- Desktop uses the original branded sidebar background artwork; at the tablet/mobile breakpoints the compact original Stockland top logo appears. Both `stockland-logo.jpg` and `stockland-sidebar-brand.png` are preserved byte-for-byte from the baseline.
- The profile dropdown remains keyboard/outside-click dismissible and is clamped to the visible viewport during display/resizing.
- The user dropdown/mobile drawer install entry invokes the real PWA installation prompt where supported; fallback instructions distinguish Windows PWA install, Android install/Add to Home Screen, and iOS/iPadOS Add to Home Screen. It explicitly avoids fake native binaries.

## Native installer decision

This cumulative plugin does **not** bundle fabricated `.exe`, `.msi`, `.dmg`, `.app`, `.ipa`, `.deb`, `.rpm`, `.appx`, `.msix`, `.apk`, or `.aab` files. A valid native Windows/macOS/Linux installer or Android/iOS package must be built for that platform with a defined wrapper, signing identity, release process and platform-specific verification. Those files are not produced simply by placing a download link in a WordPress plugin. This release uses the genuine PWA manifest/service-worker/install event already integrated with the ERP, so it installs a standalone window/icon while retaining the online ERP data source. Browser support and actual device acceptance have to be verified on the target device/browser.

## Code preservation and scope

Compared with the 16.1.88 baseline package, the file inventory is unchanged at 74 files. Fifteen existing files differ: README/manifest, app JS/PWA JS, six ordered CSS modules and compiled CSS, CSS module metadata, and the two release-identity PHP files. **Only** `stockland-inventory.php` and `includes/class-stockland-frontend.php` changed among PHP files; only plugin version and frontend cache/route version markers changed there. All other 45 PHP files are byte-identical.

Bootstrap stylesheet `assets/bootstrap.min.css`, the original logo image, and desktop sidebar brand artwork have byte-identical hashes to the prior package. DB/Core version remains `16.1.1`, Accounting `16.1.15`, HR `16.1.3`. The 49-table contract (29 Core/Operations + 16 Accounting/Procurement + 4 HR/Payroll) is unchanged. No Order/PLEDGER/GL posting, role authority, audit-chain, payroll calculation, REST controller, or business SOP file was changed.

## Test results

- 47/47 PHP files pass syntax check.
- Payroll/System Health: 22/22 assertions.
- Notification renderer: 15/15.
- Payroll preview: 6 assertions.
- Release invariants: 17/17.
- CSS source module build, minification equivalence and PostCSS parse: pass.
- Header/theme responsive matrix: 12 widths (1920, 1600, 1440, 1280, 1024, 991, 768, 576, 430, 390, 360, 320); zero document-level overflow, zero page/console errors, profile dropdown remained in viewport and Escape closed it. Desktop sidebar logo background and compact tablet/mobile logo display correctly at their respective breakpoints.
- Four computed themes return distinct primary colors, header accents, nav colors and matching browser theme-color values.
- PWA browser-event contract test: desktop install prompt accepted/removes control; Android fallback in mobile drawer gives correct standalone app guidance and says it does not download an APK; help dismissal works.

These browser tests use a synthetic WordPress API and simulated install events. They do not replace a signed-in live-site acceptance run or physical-device install test.

## Release gates not completed

- Exact-version GitHub Actions/PHPCS/WPCS/Semgrep: pending due read-only repository access in this session.
- Live WordPress/MariaDB install, live deep integrity/audit-chain scan, physical device install acceptance, backup restore and rollback: pending. The live site was not accessed or changed.
- This package is **locally validated, not live-production certified**. Back up and verify restoration, then stage the ZIP and check all four themes, logo/header behavior, notifications, Order/PLEDGER/GL actions and install flow on real target devices before any production replacement.


## 2026-10-20 — Verified GitHub package gate and CAP status

**Exact-version CI evidence:** [GitHub Actions run #6 — PASS](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/39333897144). Candidate branch: `audit/release-gates-16.1.89`; validation code commit: `1308376345d4552f1ecec410b9b3d8a3f688c15a`.

The GitHub runner independently verified the committed installer bytes:
- SHA-256 matches `4b8a5fe5648ddf062953c671fe572cb62235757d3a092da6b23116aacae392c3`; size 1,104,097 bytes.
- ZIP CRC passes; 74 files under the single `stockland-erp-app/` root; no duplicate or parent-traversal paths.
- PHP syntax: 47/47 passed under PHP 8.4; `assets/app.js` and `assets/pwa.js` passed Node syntax checks.
- Release/PWA/CSS manifests parse; plugin version, route/cache marker, Bootstrap 5.3.8 Git-blob/SRI, six-module CSS build/minified output, four theme identifiers, PWA install event and 49-table contract all pass.

### Corrective-action register — evidence-based disposition

| CAP | Status | Evidence / remaining condition |
|---|---|---|
| CAP-01 — Exact artifact identity and checksum | **CLOSED** | GitHub Actions independently computed the archive SHA-256 and size and passed ZIP integrity/inventory checks in run #6. |
| CAP-02 — Exact-version source/package CI | **PARTIALLY CLOSED** | Repeatable GitHub package gate now passes PHP/JS syntax and release/CSS/schema invariants. PHPCS/WPCS, Semgrep and the full business-workflow regression suite are still pending; do not mark the entire CAP closed. |
| CAP-03 — Live deep-integrity/audit-chain and ledger reconciliation | **OPEN** | Run the full authenticated live scan; the earlier health report explicitly deferred these checks. |
| CAP-04 — Staging installation and real Owner/Management/Warehouse/Branch/Sales/Finance/HR workflow acceptance | **OPEN** | Not exercised by this static package gate. |
| CAP-05 — Verified backup restore and rollback | **OPEN** | No restore/rollback evidence exists yet. |
| CAP-06 — Physical-device PWA installation | **OPEN** | Current test verifies browser contract/syntax only, not an actual target device. |
| CAP-07 — Payroll compensation records and FK metadata | **OPEN** | Requires authorized record-level review and independent statutory sign-off; no payroll or schema data were changed. |
| CAP-08 — Plugin-source corrective fixes | **BLOCKED** | This PR adds reproducible CI gates only. It does not alter the installer or ERP business source. A defect-specific patch must be tied to an exact source finding and regression test before it can be declared fixed. |

**Local repeat command:** with Python 3.12+, PHP 8.4 and Node.js 22 available, run `python ci/validate_release_16_1_89.py` from the repository root. It validates the currently committed ZIP; CI does not install WordPress or write business data.

**Safety boundary:** no installer binary was replaced, no production WordPress install or database mutation was performed, and no live-production certification is claimed. Only CAP-01 and the stated package-gate portion of CAP-02 are closed/passed.
