# Security boundary

- Bearer-token authentication uses constant-time comparison.
- A token is generated on activation and stored in a plugin-owned WordPress option; it is never committed to GitHub.
- Only WordPress administrators can view or rotate the token.
- Fixed MCP tool allow-list; unknown tools are rejected.
- Product tools return active catalog identity fields only; no costs, selling prices, inventory balances, employees, payroll, customers, suppliers, financial records or audit payloads.
- SQL uses prepared statements, fixed table/column identifiers and bounded result limits.
- No business DML, ERP schema DDL, migration runner, transaction posting or approval route exists.
- No arbitrary SQL, table name, REST proxy or dynamic PHP execution.
- Use HTTPS only. Treat the token as a password and restrict it to the intended GitBook site.
- The existing published GitBook manual remains the SOP source of truth; this bridge supplements it with live catalog facts.
