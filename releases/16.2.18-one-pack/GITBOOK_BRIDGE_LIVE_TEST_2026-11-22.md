# GitBook read-only bridge 1.0.0 — live test on ERP 16.2.18 (22 Nov 2026)
Bridge ZIP SHA-256 `42c947776332f385c8cfd6e41bee1eea3d9c46e8400e9c4fdf02805ffa194c6f` (built from branch integration/stockland-gitbook-readonly-bridge-16.2.18 @ 739ecb9 with the CI packaging recipe; deterministic). The `5acb0e51…` hash in the branch's AUDIT.md belongs to an earlier CI build.
Environment: disposable WordPress 7.1.3, PHP 8.3.6, MySQL 8.0.46, ERP 16.2.18 (86 tables, after full E2E), loopback only.
| Check | Result |
|---|---|
| Add-on tests: 7 contract tests, MCP harness, PHP lint | PASS |
| Install + activate beside ERP; no table created (98 tables before and after) | PASS |
| /health public, no business data | PASS |
| MCP without token / wrong token | 401 / 401 PASS |
| initialize, tools/list (exactly 4 tools) | PASS |
| stockland_system_status: ERP 16.2.18, read_only, no migrations, no writes | PASS |
| product_search and sku_lookup return live Product Master identity; no cost, price or stock fields; unknown SKU handled; SQL-injection string returns empty | PASS |
| import_contract: field list and policy text only; no cost/price values | PASS |
| Write-style tool call (stockland_create_sale) | refused: unknown tool PASS |
| GET on the MCP route | 404 PASS |
NOT VERIFIED: registration in the GitBook site, GitBook Ask end to end, the public manual site content (not reachable from the test workspace), behavior on the live host over HTTPS, the exact-version guard against a mismatching ERP.
