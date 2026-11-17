# STANDARDS\_AND\_LOCALIZATION

This document records candidate references for the Apparel Supply Chain product. It does not claim certification, grant permission to reproduce licensed standards, or replace buyer, regulatory, or local professional approval. The standards registry stores edition, territory/adoption, applicable product/process scope, licensed source, owner, effective date, and review status.

## 1. Clothing body measurements and size designation

* **ISO 8559-1:2017 — Size designation of clothes, Part 1: Anthropometric definitions for body measurement.** ISO currently lists it as published and confirmed in 2026. It describes anthropometric measurements as a basis for physical/digital body measurement databases and clothing size/shape profiles.
* **ES ISO 8559-1:2021** is published by the Ethiopian Standards Institute and provides the Ethiopian adoption referenced in this workspace.
* **ISO 8559-2:2025** appears in the current ISO/TC 133 catalogue as the newer Part 2 for primary/secondary dimension indicators.
* **ISO 8559-3:2018** is listed by ISO/TC 133 for methodology to create body measurement tables and intervals.

**Product decision:** Store body anthropometry, finished-garment measurement spec, ease, buyer tolerances, and size labels as separate data. A standard body measurement is not automatically the garment's finished dimension or buyer tolerance. A tenant chooses the relevant market/size system and records the adopted edition/references. The full standard text must be legally obtained by the customer; store references and mappings, not reproduced standard content.

### Sources

* https://www.iso.org/standard/61686.html
* https://www.ethiostandards.org/sites/default/files/2021-02/3%2C%20%20ES%20ISO\_8559-1\_2021.pdf
* https://www.iso.org/committee/52374/x/catalogue/

## 2. Acceptance sampling for quality inspection

* **ISO 2859-1:2026, Edition 3** was published January 2026 and defines sampling schemes indexed by Acceptance Quality Limit for lot-by-lot inspection. ISO describes it as replacing the 1999 edition and its amendments.

**Product decision:** Quality plans can reference ISO 2859-1 or a buyer-specific approved inspection protocol, but AQL, inspection level, lot size, normal/tightened/reduced switching state, sample size, and acceptance/rejection numbers are configured per buyer/product/market and effective date. Never hard-code a universal AQL. Validate that the tenant is licensed/authorized to use the selected standard and has an owner-approved inspection plan.

### Source

https://www.iso.org/standard/2859-1

## 3. Logistic unit identity and carton traceability

* Use GS1 standards only where the tenant owns or is licensed for the required GS1 company prefix/identifiers.
* SSCC is a unique reference for a logistic unit; it can connect the physical carton/pallet to electronic trade/shipping records.
* A warehouse/logistics system generates and archives SSCCs. The ERP must not issue customer SSCCs using Stockland or a SaaS provider's GS1 prefix.

**Product decision:** Store SSCC, logistic-unit identity, carton contents, tenant's GS1 company prefix source, and assignment/audit record. Ensure uniqueness in the tenant's registered issuer scope and retain old SSCC for shipment/history. If a tenant does not use GS1 SSCC, a tenant-scoped unique internal carton ID remains available.

### Sources

* https://www.gs1.org/docs/tl/GS1\_Logistic\_Label\_Guideline.pdf
* https://ref.gs1.org/standards/genspecs/

## 4. CAD and pattern file interchange

Pattern CAD interchange is vendor/dialect-specific. The system must:

* Preserve original file bytes and SHA-256.
* Record source application/version, dialect, declared units, scale, and conversion tool/version.
* Convert supported geometry to Stockland's vendor-neutral canonical representation.
* Record unsupported entities or lossy transformations; fail closed if the pattern boundary, grainline, notches, or grade nest cannot be validated.
* Test round-trip tolerance against the tenant's actual CAD/CAM tool before enabling production export.

DXF-AAMA/ASTM is a candidate adapter family discussed in garment development materials, but this workspace does not treat a secondary educational source as the licensed normative text. Verify the current official format specification, license, vendor support, and actual customer files before committing to a dialect or claiming interoperability.

**Reference overview (secondary/educational only):** https://garment.dev/learn/dxf-aama-astm

## 5. Fabric and material test references

Material attributes may link to approved methods for fibre/yarn/fabric testing, but the system should not invent test method results. Example: ISO 2060:1994 covers determination of yarn linear density by skein method and is listed by ISO as current/confirmed after review in 2025. It is not a garment defect or fabric quality acceptance standard.

### Source

https://www.iso.org/standard/6837.html

## 6. Ethiopian and other country localization

For Ethiopia and any other market, the country pack must version:

* Tax, VAT, customs/export, statutory payroll/PAYE/pension, employment/labour, leave, working-time, and other legal calculation rules.
* Country currency, timezone, language, fiscal/calendar periods, invoice numbering, tax registration, and registration-required fields.
* Market labels, care symbols, fiber composition, origin, buyer-required restricted substance/test certificates, and export documents.

{% hint style="warning" %}
No payroll rate, tax band, pension contribution, exemption cap, employment entitlement, or legal interpretation is invented in this product spec.
{% endhint %}

The Ethiopian statutory pack requires exact official effective-dated references, qualified local professional review, sample calculations, and independent Owner/HR/Finance approvals before production calculation/finalization can be enabled. Bangladesh settings or examples from BengalTex are not applied to Ethiopian tenants.

## 7. Standards registry model

Each standard/rule reference should store:

`standard_id`, issuing body, designation, edition/year, status/effective date, territory, adoption, language, licensed document URI/reference, owning role, applicable domain/process, customer/buyer override, versioned test plan, review date, and evidence of approval.

{% hint style="warning" %}
A record marked `REFERENCE_ONLY` or `DRAFT` cannot authorize production or payroll behavior.
{% endhint %}

Standards do not self-apply; an approved customer/company rule/profile binds the standard reference to a particular use case and effective date.
