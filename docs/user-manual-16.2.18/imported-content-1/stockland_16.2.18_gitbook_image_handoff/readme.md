# README

This bundle inventories **17 image assets already used by the published Stockland ERP 16.2.18 manual** and tells the GitBook agent where each asset belongs. It also contains the S01–S36 capture checklist, role/workflow map, and a source downloader.

## Important: what is and is not inside this ZIP

The original image binaries are **not embedded in this ZIP**. The connected GitBook service returned each file's exact name, GitBook file ID, MIME type, byte size, dimensions where recorded, and direct storage URL. The current ChatGPT transfer runtime could not fetch the image bytes from GitBook's storage host. It would be misleading to pretend that image files are embedded.

Run `python download_assets.py` in a computer or agent environment with outbound HTTPS access to GitBook's public asset host. It downloads the exact original files into `assets/`, checks content type and size, computes SHA-256, and writes `assets/DOWNLOAD_RESULTS.csv`. Four files did not have dimensions in GitBook's metadata; the script measures them after download if Pillow is installed (`python -m pip install Pillow`).

## Package contents

* `AGENT_INSTRUCTION.md` — copy/paste task instruction for the GitBook agent.
* `MARKDOWN_INSERTION_MAP.md` — exact current page paths, headings, and insertion positions for all 17 assets, including re-used images.
* `ROLE_WORKFLOW_MATRIX.md` — which roles and operational workflows are represented.
* `CAPTURE_GAPS_S01-S36.csv` — exact screenshot IDs and current evidence status.
* `ASSET_MANIFEST.csv` / `.json` — asset IDs, URLs, byte sizes, metadata, roles, status, and destinations.
* `SOURCE_ASSET_URLS.txt` — direct source URLs for retrieval.
* `download_assets.py` — downloader; it does not edit GitBook or change the ERP.

## Guardrails

The ERP's sole certified live production baseline remains **16.2.18**. This package is documentation-only. Do not change the release branch, installer, live database, production records, or user permissions. Several images use synthetic QA fixture values and must keep their existing caveat captions. They show interface layout only—not successful transactions, live records, health-check results, authorization, release readiness, or schema state.

For desktop screenshots, target at least **1440 px wide** or retain the full native resolution if the actual app is narrower. Never upscale these originals and describe the result as a high-resolution capture. Capture fresh evidence from the exact installed 16.2.18 frontend.
