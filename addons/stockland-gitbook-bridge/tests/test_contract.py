import pathlib, re, unittest
ROOT = pathlib.Path(__file__).resolve().parents[1]
PHP = (ROOT / "stockland-gitbook-bridge.php").read_text(encoding="utf-8")

class Contract(unittest.TestCase):
    def test_identity(self):
        self.assertIn("Plugin Name: Stockland ERP GitBook Read-only Bridge", PHP)
        self.assertIn("Version: 1.0.0", PHP)

    def test_no_schema_or_business_mutations(self):
        for item in ("CREATE TABLE", "ALTER TABLE", "DROP TABLE", "TRUNCATE TABLE", "DELETE FROM", "INSERT INTO"):
            self.assertNotIn(item, PHP.upper())
        self.assertIsNone(re.search(r"\bUPDATE\s+[^;]+\bSET\b", PHP, re.I))
        self.assertNotIn("dbDelta(", PHP)
        self.assertNotIn("Stockland_Installer", PHP)

    def test_fixed_tool_allowlist(self):
        for item in ("stockland_system_status", "stockland_product_search", "stockland_sku_lookup", "stockland_product_import_contract"):
            self.assertIn(item, PHP)
        for item in ("stockland_post", "stockland_approve", "stockland_create_transaction"):
            self.assertNotIn(item, PHP)

    def test_auth_and_bounded_query(self):
        self.assertIn("hash_equals( $expected", PHP)
        self.assertIn("const MAX_LIMIT = 50", PHP)
        self.assertIn("LIMIT %d", PHP)
        self.assertIn("'16.2.18' !== STOCKLAND_VERSION", PHP)

    def test_readonly_catalog_fields(self):
        block = PHP[PHP.find("private function product_search"):PHP.find("private function sku_lookup")]
        self.assertNotIn("purchase_cost", block)
        self.assertNotIn("retail_price", block)
        self.assertNotIn("quantity_on_hand", block)

    def test_no_manual_change(self):
        self.assertIn("docs-user-manual-16.2.18", PHP)

if __name__ == "__main__":
    unittest.main()
