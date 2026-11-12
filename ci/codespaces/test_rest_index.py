#!/usr/bin/env python3
"""Unit tests for the Stockland .13/.15 REST index contract."""
from __future__ import annotations

import json
import tempfile
import unittest
from unittest.mock import patch

from check_rest_index import EXPECTED_ROUTES, load_document, validate_index


def valid_index() -> dict:
    routes = {
        route: {"endpoints": [{"methods": sorted(methods)}]}
        for required in EXPECTED_ROUTES.values()
        for route, methods in required.items()
    }
    return {
        "namespaces": ["stockland/v1", "stockland/v16", "wp/v2"],
        "routes": routes,
    }


class RestIndexContractTests(unittest.TestCase):
    def test_accepts_both_candidate_namespaces_and_routes(self) -> None:
        messages = validate_index(valid_index())
        self.assertEqual(len(messages), 2)
        self.assertIn("/stockland/v1/app/bootstrap [GET]", messages[0])
        self.assertIn("/stockland/v16/system/ping [GET]", messages[1])

    def test_rejects_wordpress_only_rest_index(self) -> None:
        with self.assertRaisesRegex(ValueError, "missing namespace stockland/v1"):
            validate_index({"namespaces": ["wp/v2"], "routes": {}})

    def test_rejects_missing_v1_bootstrap(self) -> None:
        document = valid_index()
        document["routes"].pop("/stockland/v1/app/bootstrap")
        with self.assertRaisesRegex(ValueError, "missing registered route /stockland/v1/app/bootstrap"):
            validate_index(document)

    def test_rejects_missing_v16_system_ping(self) -> None:
        document = valid_index()
        document["routes"].pop("/stockland/v16/system/ping")
        with self.assertRaisesRegex(ValueError, "missing registered route /stockland/v16/system/ping"):
            validate_index(document)

    def test_rejects_wrong_http_method(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v16/system/ping"] = {
            "endpoints": [{"methods": ["POST"]}]
        }
        with self.assertRaisesRegex(ValueError, "missing expected methods"):
            validate_index(document)

    def test_aggregates_methods_from_multiple_endpoint_definitions(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v1/app/bootstrap"] = {
            "endpoints": [{"methods": ["POST"]}, {"methods": "get"}]
        }
        self.assertEqual(len(validate_index(document)), 2)

    def test_rejects_route_without_endpoint_metadata(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v1/app/bootstrap"] = {"endpoints": []}
        with self.assertRaisesRegex(ValueError, "no endpoint definitions"):
            validate_index(document)

    def test_rejects_misleading_master_installer_setup_only_routes(self) -> None:
        document = {
            "namespaces": ["stockland/v16"],
            "routes": {
                "/stockland/v16/setup/status": {"endpoints": [{"methods": ["GET"]}]},
                "/stockland/v16/setup/advance": {"endpoints": [{"methods": ["POST"]}]},
                "/stockland/v16/app/access/bootstrap": {"endpoints": [{"methods": ["GET"]}]},
            },
        }
        with self.assertRaisesRegex(ValueError, "missing namespace stockland/v1"):
            validate_index(document)

    def test_loads_utf8_bom_rest_index_json(self) -> None:
        with tempfile.NamedTemporaryFile("wb") as body:
            body.write(bytes.fromhex("efbbbf") + json.dumps(valid_index()).encode("utf-8"))
            body.flush()
            with patch("sys.argv", ["check_rest_index.py", body.name]):
                self.assertEqual(len(validate_index(load_document())), 2)

    def test_rejects_non_object_rest_index(self) -> None:
        with tempfile.NamedTemporaryFile("w", encoding="utf-8") as body:
            json.dump(["not", "an", "object"], body)
            body.flush()
            with patch("sys.argv", ["check_rest_index.py", body.name]):
                with self.assertRaisesRegex(ValueError, "REST index JSON must be an object"):
                    load_document()

    def test_invalid_json_error_includes_response_preview(self) -> None:
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
