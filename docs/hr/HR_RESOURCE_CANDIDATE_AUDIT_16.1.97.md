# ERP 16.1.97 — HR Process Center Resource Candidate Static Audit

**Scope:** package-only inspection of the candidate ZIP. This does not prove installation parity or live browser behavior.

## Exact candidate

- ZIP: `STOCKLAND_ERP_16.1.97_HR_PROCESS_CENTER_CANDIDATE.zip`
- SHA-256: `7822606ded2883d9d5faf8b58962f058c99320d64fcf2da50c9f140448adee30`
- Size: 2,408,034 bytes; 107 unique files; 80 PHP files.
- HR resource definitions: 30; embedded DOCX payloads whose declared size, SHA-256, ZIP signature and Word document XML passed: 30/30.

## Source-level checks passed

- The plugin entry point includes the resource-library class and HR REST controller, and constructs the controller.
- Authenticated `GET /hr/resources` and `GET /hr/resources/{id}/download` routes exist with server-side `Stockland_Authority::can_view_hr()` permission callbacks.
- The download path validates an allow-listed resource ID/path, expected size and SHA-256, then fails closed if its audit event cannot be written.
- The frontend's HR renderer invokes resource loading; the catalogue renderer binds click handlers to the download buttons; the handler calls the endpoint, reads base64 payload, creates a DOCX Blob and triggers download; error feedback is present.

## Interpretation

The candidate source is not “only a .doc with no handler.” However, the live report that the button is nonresponsive remains unresolved because the currently installed bytes, browser behavior, REST response, authentication nonce and deployed file parity have not been independently tested in this session. Diagnose on a restored staging clone: verify installed plugin/file hashes, load HR as an authorized user, capture the exact request/status/body, test anonymous/unauthorized requests, click at 320/360/390 px and desktop, and verify saved filename/MIME/size/hash. Do not claim the source audit alone fixes the live site.

This resource-library candidate does **not** implement the full HR/payroll lifecycle and is not production-approved.
