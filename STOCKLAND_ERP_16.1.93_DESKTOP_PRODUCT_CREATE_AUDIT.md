# Stockland ERP 16.1.93 — Desktop Product Master Create Button Audit

## Summary

A normal clean-package Chromium smoke test showed that `#newProduct` is visible and responds on wide desktop viewports. It did **not** reproduce the owner's live-site failure on its own, so this audit does not claim the live host was inspected or that a production root cause is confirmed.

A controlled overlap test reproduced the relevant failure class: when host/WordPress theme CSS stretches the global-search popup across the page-title action row, `document.elementFromPoint()` resolves to a search-result element, not `#newProduct`; a click cannot open the form. The editor's form handler is present, so this failure occurs before the create form / REST save chain.

## Focused fix in the isolated 16.1.93 candidate

The existing capture-phase delegated click handler is retained as defense against node-level handler replacement. A scoped desktop rule is added at runtime to the title-action row on `min-width: 992px`: relative stacking context, z-index 80, isolation, and explicit pointer events. The desktop toolbar remains the lower stacking context (z-index 50) while the Product Master actions are raised above it. Existing modal overlays remain at the higher z-index they already use. CSS source/compiled files are not changed; release/cache metadata behavior and all server-side workflows are preserved.

This change is narrower than raising the entire page or application shell: it raises only page-title actions, so regular content/table rows, modal layering, and mobile layout are unaffected.

## Browser reproduction and regression test

`ci/qa/test_desktop_product_create.py` runs in headless Chromium against the candidate ZIP with every ERP REST request intercepted and answered from in-memory fixtures.

For each desktop viewport (1024, 1280, 1366, 1440, 1920, 2560 and 3440 CSS pixels), it:
1. Opens Product Master as a mocked Owner.
2. Injects an explicit host-theme search popup overlap across the page title, then opens global search results.
3. Verifies the top hit target at the center of `+ New Product / Variant` is the button, not the popup, and checks the runtime layering guard.
4. Clicks Create Product, verifies the form and submit handler bind, fills only test values, then checks for exactly one mocked `POST /products`, modal close and success acknowledgement.
5. Fails on JavaScript exceptions.

No real ERP or database endpoint is called. It does not create a live product.

## Static chain and preservation checks

The existing exact-package gate verifies the 16.1.92 baseline checksum, candidate ZIP integrity, product click guard, report permissions, schema activator unchanged, 49-table contract, no database-version changes, and byte-identical source/compiled CSS. The new Playwright test adds an actual hit-test/click/form-submit regression, rather than relying solely on text matching.

## Limitations / rollout

The live URL and authenticated live desktop cannot be inspected from this runner. This establishes a reproduced failure mechanism and verifies the fix in a deterministic host-theme overlap harness, but the exact source of the owner's installed-theme overlay remains unconfirmed. The pull request remains draft and nothing is deployed. Complete authenticated staging verification using the real desktop browser/theme and an Owner/Management/branch role before release.
