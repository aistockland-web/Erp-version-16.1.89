# WordPress + dbDelta runtime test boundary

- Every WordPress install and database created by this harness is synthetic and disposable.
- No production URL, credential, business backup, employee/customer record, private HR document or live database connection is used.
- No real sale, payroll, payment, inventory movement, journal or ledger transaction is created.
- Candidate upgrade/fresh-install sites use separate temporary directories and unique database names; the plugin stays active during in-place update tests.
- The private browser preview is restored to protected 16.1.95 after the run, including after candidate failure.
- MariaDB 10.11 and MySQL 8.0 are separate mandatory lanes. A MySQL failure remains visible even if MariaDB passes.
- The pre-fix 16.2.9 package is used only to reproduce the unsafe APPROVED migration default; the corrected package must preserve DRAFT and null approver/timestamp.
- The exact 16.2.13 operational source is not available as a pinned artifact here; its runtime state is NOT RUN until recovered.
- Passing characterization does not approve the candidate's provisional schema, HR architecture, statutory calculations or production deployment.
