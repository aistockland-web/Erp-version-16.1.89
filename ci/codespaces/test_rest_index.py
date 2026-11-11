#!/usr/bin/env python3
"""Unit tests for the runtime Stockland REST index contract."""
from __future__ import annotations

import unittest
from unittest.mock import patch

from check_rest_index import EXPECTED_ROUTES, load_document, validate_index


def valid_index() -> dict:
    routes = {
        route: {"endpoints": [{"methods": ["GET"]}]}
        for expected in EXPECTED_ROUTES.values()
        for route in expected
    }
    return {
        "namespaces": ["stockland/v1", "stockland/v16", "wp/v2"],
        "routes": routes,
    }


class RestIndexContractTests(unittest.TestCase):
    def test_accepts_both_stockland_namespaces_and_expected_routes(self) -> None:
        messages = validate_index(valid_index())
        self.assertEqual(len(messages), 2)

    def test_rejects_wordpress_only_rest_index(self) -> None:
        document = {"namespaces": ["wp/v2"], "routes": {}}
        with self.assertRaisesRegex(ValueError, "missing namespace"):
            validate_index(document)

    def test_rejects_namespace_without_expected_route(self) -> None:
        document = valid_index()
        document["routes"].pop("/stockland/v16/system/ping")
        with self.assertRaisesRegex(ValueError, "missing registered route"):
            validate_index(document)

    def test_rejects_route_without_endpoint_metadata(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v1/app/bootstrap"] = {"endpoints": []}
        with self.assertRaisesRegex(ValueError, "no endpoint definitions"):
            validate_index(document)

    def test_loads_utf8_bom_rest_index_json(self) -> None:
        import json
        import tempfile
        with tempfile.NamedTemporaryFile("wb") as body:
            body.write(bytes.fromhex("efbbbf") + json.dumps(valid_index()).encode("utf-8"))
            body.flush()
            with patch("sys.argv", ["check_rest_index.py", body.name]):
                self.assertEqual(len(validate_index(load_document())), 2)

    def test_rejects_non_object_rest_index(self) -> None:
        import json
        import tempfile
        with tempfile.NamedTemporaryFile("w", encoding="utf-8") as body:
            json.dump(["not", "an", "object"], body)
            body.flush()
            with patch("sys.argv", ["check_rest_index.py", body.name]):
                with self.assertRaisesRegex(ValueError, "REST index JSON must be an object"):
                    load_document()

    def test_invalid_json_error_includes_response_preview(self) -> None:
        import tempfile
        with tempfile.NamedTemporaryFile("w", encoding="utf-8") as body:
            body.write("<html>WordPress error</html>")
            body.flush()
            with patch("sys.argv", ["check_rest_index.py", body.name]):
                with self.assertRaisesRegex(ValueError, "response preview"):
                    load_document()

    def test_rejects_untyped_rest_index(self) -> None:
        with self.assertRaisesRegex(ValueError, "namespaces array"):
            validate_index({"namespaces": {}, "routes": []})


if __name__ == "__main__":
    unittest.main()
