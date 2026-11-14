# Stockland ERP — Cloudflare Free-Plan Architecture & 16.2.18 Environment

**Date:** 14 November 2026  
**Canonical application:** Stockland Ethiopia ERP / WordPress plugin 16.2.18  
**Canonical repository:** `aistockland-web/Erp-version-16.1.89`  
**Canonical branch used for this infrastructure work:** `delivery/stockland-full-erp-completion-2026-11-11`  
**Infrastructure-only work branch:** `infra/stockland-cloudflare-free-and-16218-codespaces-2026-11-14`

## 1. Non-negotiable release and architecture decisions

- Keep the existing, Owner-designated production release at **16.2.18**. This target does not rebuild, re-version, replace, downgrade, migrate, or rewrite the application package.
- Use the exact approved GitHub Actions installer artifact from [run 43570216177](https://github.com/aistockland-web/Erp-version-16.1.89/actions/runs/43570216177), artifact **14104031868**.
- Exact inner installer: `STOCKLAND_ERP_16.2.18_MASTER_WORDPRESS_INSTALLER.zip`, 2,251,794 bytes, SHA-256 `de7b087906e82185de1782af1125f3194c6904785afb5338e0c828c743d38a08`.
- Artifact wrapper SHA-256: `ff58626d39c63980eba7f3de5cf8e2ee7c5788952e746731791fdd20b434d133`.
- Embedded package manifest identifies source commit `387c193c7999840c731d37acd854ec920f134507`, 124 inventoried application files. The ZIP has 125 entries, one `stockland-erp-app/` root, successful CRC validation, and every manifest file hash matches.
- WordPress/PHP/MySQL remains the ERP application and transactional system of record. Cloudflare Workers, D1, KV, and R2 are not replacements for WordPress authentication, MySQL transactions, the Stockland stock ledger, finance journals, or audit history.
- Cloudflare Containers are **not included in Workers Free**: the current Containers pricing page shows Free as N/A and the included 25 GiB-hours, 375 vCPU-minutes and 200 GB-hours are part of the $5/month Workers Paid plan. Do not deploy the ERP stack to Containers on this Free-only project. Use repository Docker Compose and GitHub Actions for disposable engineering environments.
- GitHub personal Free includes 120 Codespaces core-hours and 15 GB-month storage. On the selected 2-core machine, that is at most 60 active hours per month before accounting for any prior account usage; storage is metered separately by GB-hours. Stop the Codespace when idle and check the actual remaining account allowance before creation. No prebuilds are configured.
- No paid Cloudflare product, subscription, or production migration is authorized by this infrastructure target.

## 2. Live Cloudflare account inventory

Read-only API inventory, followed by one narrowly scoped observability privacy update:

| Resource | Observed state |
|---|---|
| Worker `erp` | Deployed; static assets present; no Worker modules; no bindings |
| Workers subdomain | `aistockland.workers.dev` account subdomain |
| Worker custom domains/routes | None returned by API |
| Worker Pages projects | 0 |
| D1 databases | 0 |
| KV namespaces | 0 |
| Queues | 0 |
| Durable Object namespaces | 0 |
| DNS zones | 0 |
| R2 | Not enabled; API returned error 10042 and instructed that R2 must first be enabled in the Cloudflare Dashboard |

The existing `erp` Worker is currently an asset-only Worker, not a PHP runtime or a WordPress/MySQL origin. Its settings show no bindings. It must not be described as a Cloudflare-hosted full WordPress ERP or as a protected proxy for the production WordPress host.

### Worker observability privacy change

The existing `erp` Worker had observability enabled but `redact_query_string=false`. This was changed to `redact_query_string=true`; the setting was read back and confirmed. Logs remain enabled with the prior sampling/persistence settings; traces remain disabled. The Worker deployment list shows the resulting API-created version at 100%. No Worker source, static asset, WordPress plugin, installer ZIP, or ERP business data was changed.

## 3. Free-plan product selection

Use only products and features with a documented Free tier, and only when they solve an actual requirement.

| Cloudflare capability | Free-plan fit | Stockland use |
|---|---|---|
| DNS, CDN, DDoS protection | Free features, but require a domain/zone under the account | Not configured yet: this account currently has zero zones |
| Free WAF baseline / Bot Fight Mode | Available on Free; DDoS mitigation is on by default | Enable after the production hostname is represented by a verified zone; test login, REST and POS paths before enforcing custom blocks |
| Workers | Free limit: 100,000 requests/day, 10 ms CPU per invocation, 128 MB memory, 50 subrequests | Keep `erp` asset-focused; do not put PHP/WordPress or ledger transactions in a Worker |
| Workers KV | Free tier: 100,000 reads/day, 1,000 writes/deletes/list operations per day, 1 GB storage | Optional later for non-authoritative cache/configuration only; no namespace is currently provisioned |
| D1 | Free tier is available, with published daily read/write and storage caps | Do not use as the ERP's primary transactional database; no D1 database is currently provisioned |
| R2 | Standard-storage free tier: 10 GB-month, 1 million Class A operations/month, 10 million Class B operations/month, free egress | Optional encrypted exports/static media only after Dashboard activation, access policy and restore test; currently unavailable through the API |
| Turnstile | Free challenge service | Candidate for login/public forms only after both client widget and mandatory server-side Siteverify validation are implemented |
| Containers | Not available on Workers Free; included usage belongs to the $5/month Workers Paid plan | Excluded; use Docker Compose locally and in CI |

Official limits and setup references:
- [Workers limits](https://developers.cloudflare.com/workers/platform/limits/)
- [Workers pricing](https://developers.cloudflare.com/workers/platform/pricing/)
- [Workers KV pricing](https://developers.cloudflare.com/kv/platform/pricing/)
- [D1 pricing and limits](https://developers.cloudflare.com/workers/platform/pricing/)
- [R2 pricing](https://developers.cloudflare.com/r2/pricing/)
- [Containers pricing](https://developers.cloudflare.com/containers/platform/pricing/)
- [GitHub Codespaces Free usage](https://docs.github.com/en/billing/concepts/product-billing/github-codespaces)
- [Codespaces included-usage guidance](https://docs.github.com/en/codespaces/troubleshooting/troubleshooting-included-usage)
- [Free-plan WAF / DDoS / Bot Fight Mode notes](https://developers.cloudflare.com/waf/feature-interoperability/)
- [Turnstile server-side validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/)

## 4. GitHub Codespaces Free budget and default profile

The devcontainer requests the smallest currently listed machine profile: **2 cores, 8 GB RAM, 32 GB disk**. For personal GitHub Free accounts, the published included allowance is **120 core-hours and 15 GB-month of storage**. A 2-core machine consumes 2 core-hours for each active hour, so 60 active hours is the theoretical monthly maximum only when the full compute allowance remains unused. Storage is a separate GB-hour/GB-month meter; custom devcontainer images, files, extensions and persisted workspace data can consume it. Existing Codespaces usage on the account reduces the remaining balance.

- Keep one Codespace only; no repository prebuild is configured.
- Stop the Codespace when not actively working; set a short personal idle timeout (15–30 minutes) in GitHub Codespaces settings.
- Check current remaining usage in GitHub **Settings → Billing and licensing → Usage** before starting it. Do not enable paid overage or create a second Codespace if the allowance is exhausted.
- This repository's custom PHP/Node Docker image may count toward storage; monitor actual GB-hour usage rather than assuming the 32 GB disk is free.
- Docker Compose's MariaDB/WordPress/MySQL test volumes are throwaway QA only and should be removed after test runs.

Official GitHub references:
- [Included plan usage: GitHub Free Codespaces 120 core-hours / 15 GB-month](https://docs.github.com/en/billing/reference/product-usage-included)
- [Codespaces billing and usage meters](https://docs.github.com/en/billing/concepts/product-billing/github-codespaces)
- [Free included usage and idle stopping guidance](https://docs.github.com/en/codespaces/troubleshooting/troubleshooting-included-usage)
- [2-core / 8 GB / 32 GB machine configuration](https://docs.github.com/en/codespaces/about-codespaces/what-are-codespaces)

## 5. Recommended runtime topology

1. **ERP application:** unchanged 16.2.18 WordPress plugin installed on the existing WordPress/PHP/MySQL production host.
2. **Cloudflare edge:** when the company supplies a controllable domain and delegates a zone, proxy the WordPress host through Cloudflare Free. Configure SSL/TLS for the actual origin capability, DDoS defaults, Free WAF baseline, Bot Fight Mode, and cache only public static assets.
3. **Static assets:** the existing `erp` Worker may continue to serve static assets on its Workers subdomain. It has no current database/API bindings and must not be treated as the ERP backend.
4. **Local engineering:** the repository Dev Container provides PHP 8.3, Node 22, Python 3.12, Composer, WP-CLI, Docker/Compose support, MariaDB 10.11, WordPress test sites, and optional MySQL 8.0.46.
5. **Hosted verification:** GitHub Actions must test the exact approved 16.2.18 artifact in disposable WordPress/MariaDB and WordPress/MySQL 8 containers. Never pass live ERP credentials or data into CI or Codespaces.

### Cache and security rules

- Cache public versioned CSS, JavaScript, fonts and images only.
- Bypass cache for `/wp-admin/*`, `/wp-login.php`, `/wp-json/*`, REST query routing, ERP AJAX/actions, POS and financial workflows, and any request carrying authenticated cookies or an Authorization header.
- Do not cache personalized HTML, inventory balances, reservations, customer/supplier records, finance reports, payroll, private HR documents, or mutation responses.
- Turnstile must be validated server-side with Siteverify. A front-end widget by itself is not an authentication control.
- Never put database credentials, WordPress salts, Turnstile secrets, or private HR encryption keys in Worker source, public environment variables, static assets, or Git.

## 6. Exact approved installer verification in Codespaces

The new helper `scripts/codespaces/verify_approved_16_2_18.sh` is designed to retrieve and test the already-approved artifact, not rebuild it:

```bash
bash .devcontainer/initialize-host.sh
docker compose --project-directory .devcontainer -f .devcontainer/docker-compose.yml --profile mysql8 up -d --build
bash scripts/codespaces/verify_approved_16_2_18.sh all
```

The helper:
- Downloads artifact 14104031868 from the pinned GitHub Actions run using an authenticated GitHub API request.
- Verifies the outer artifact digest and exact inner ZIP SHA-256.
- Checks the 16.2.18 release manifest, root, CRC, archive paths, 124 file hashes and plugin version markers.
- Uses the isolated MariaDB 10.11 and MySQL 8.0.46 WordPress Compose sites.
- Resets only the explicitly designated disposable databases, installs the exact approved ZIP through WP-CLI, and runs the existing synthetic ERP E2E suites.
- Uses an allowlist for the internal Compose DB hostnames; normal verifier runs continue to require loopback unless the explicit Codespaces Compose mode is set.

It does not connect to the production WordPress host, change production tables, rebuild the plugin ZIP, or publish a new release.

## 7. Remaining external prerequisites

- The current Cloudflare account has no DNS zone. Therefore production DNS proxying, zone-level WAF/cache rules and SSL settings cannot be applied until the Owner supplies a domain that the account controls and completes the required nameserver delegation.
- R2 requires one-time Dashboard activation before it can be used; it is not currently provisioned.
- The Owner's live Codespace terminal is not exposed through the available GitHub repository connector. The same Docker Compose verification is therefore wired into GitHub Actions for a reproducible hosted check; the account owner can also run the command above in the interactive Codespace.
- No Cloudflare Container instance, paid Workers plan, D1 database, KV namespace, Queue, Durable Object, Pages project, production DB connection, or origin-domain change was created by this target.

## 8. Evidence and change boundaries

- Canonical application source branch/HEAD used as the integration base: `delivery/stockland-full-erp-completion-2026-11-11` / `e163f3fac0324c19f0d7fa19201b0f7f40d8263c`.
- Approved release artifact source: GitHub Actions run 43570216177, artifact 14104031868.
- Cloudflare change: Worker observability query-string redaction only.
- GitHub changes: infrastructure and disposable verification scripts/workflow/documentation only.
- No application PHP/JS/CSS/SQL, installer ZIP, release tag, live WordPress site, or production database is modified by this target.
