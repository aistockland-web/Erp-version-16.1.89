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
