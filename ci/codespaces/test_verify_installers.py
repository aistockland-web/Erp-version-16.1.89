import unittest
from verify_installers import (
    PLUGIN_ROOT,
    VerificationError,
    ensure_unique_member_paths,
    validate_member_path,
)


class ArchivePathPolicyTests(unittest.TestCase):
    def test_root_directory_is_allowed(self):
        self.assertEqual(validate_member_path("stockland-erp-app/"), [])

    def test_nested_runtime_file_is_allowed(self):
        self.assertEqual(
            validate_member_path("stockland-erp-app/includes/class-stockland-db.php"),
            ["includes", "class-stockland-db.php"],
        )

    def test_parent_traversal_is_rejected(self):
        with self.assertRaises(VerificationError):
            validate_member_path("stockland-erp-app/../outside.php")

    def test_empty_path_segments_are_rejected(self):
        with self.assertRaises(VerificationError):
            validate_member_path("stockland-erp-app//outside.php")

    def test_absolute_paths_are_rejected(self):
        with self.assertRaises(VerificationError):
            validate_member_path("/stockland-erp-app/plugin.php")

    def test_wrong_root_is_rejected(self):
        with self.assertRaises(VerificationError):
            validate_member_path("other-plugin/plugin.php")

    def test_duplicate_paths_are_rejected(self):
        with self.assertRaises(VerificationError):
            ensure_unique_member_paths([
                "stockland-erp-app/plugin.php",
                "stockland-erp-app/plugin.php",
            ])

    def test_policy_root_constant(self):
        self.assertEqual(PLUGIN_ROOT, "stockland-erp-app/")


if __name__ == "__main__":
    unittest.main()
