#!/usr/bin/env python3
"""Unit tests for the Stockland .13/.15 REST index contract."""
from __future__ import annotations

import json
import tempfile
import unittest
from pathlib import Path
from unittest.mock import patch

from check_rest_index import EXPECTED_ROUTES, load_document, validate_index


def valid_index() -> dict:
    routes = {
        route: {"endpoints": [{"methods": sorted(methods)}]}
        for required in EXPECTED_ROUTES.values()
        for route, methods in required.items()
    }
    return {
        "namespaces": ["stockland/v16", "wp/v2"],
        "routes": routes,
    }


class RestIndexContractTests(unittest.TestCase):
    def test_accepts_observed_candidate_setup_routes(self) -> None:
        messages = validate_index(valid_index())
        self.assertEqual(len(messages), 3)
        self.assertIn("/stockland/v16/setup/status [GET]", messages[0])
        self.assertIn("/stockland/v16/setup/advance [POST]", messages[1])
        self.assertIn("/stockland/v16/app/access/bootstrap [GET]", messages[2])

    def test_rejects_wordpress_only_rest_index(self) -> None:
        with self.assertRaisesRegex(ValueError, "missing namespace stockland/v16"):
            validate_index({"namespaces": ["wp/v2"], "routes": {}})

    def test_rejects_missing_setup_status(self) -> None:
        document = valid_index()
        document["routes"].pop("/stockland/v16/setup/status")
        with self.assertRaisesRegex(ValueError, "missing registered route /stockland/v16/setup/status"):
            validate_index(document)

    def test_rejects_missing_setup_advance(self) -> None:
        document = valid_index()
        document["routes"].pop("/stockland/v16/setup/advance")
        with self.assertRaisesRegex(ValueError, "missing registered route /stockland/v16/setup/advance"):
            validate_index(document)

    def test_rejects_missing_access_bootstrap(self) -> None:
        document = valid_index()
        document["routes"].pop("/stockland/v16/app/access/bootstrap")
        with self.assertRaisesRegex(ValueError, "missing registered route /stockland/v16/app/access/bootstrap"):
            validate_index(document)

    def test_rejects_wrong_http_method(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v16/setup/advance"] = {
            "endpoints": [{"methods": ["GET"]}]
        }
        with self.assertRaisesRegex(ValueError, "missing expected methods"):
            validate_index(document)

    def test_aggregates_methods_from_multiple_endpoint_definitions(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v16/setup/status"] = {
            "endpoints": [{"methods": ["POST"]}, {"methods": "get"}]
        }
        self.assertEqual(len(validate_index(document)), 3)

    def test_rejects_route_without_endpoint_metadata(self) -> None:
        document = valid_index()
        document["routes"]["/stockland/v16/app/access/bootstrap"] = {"endpoints": []}
        with self.assertRaisesRegex(ValueError, "no endpoint definitions"):
            validate_index(document)

    def test_rejects_stale_v1_bootstrap_and_system_ping_contract(self) -> None:
        document = {
            "namespaces": ["stockland/v1", "stockland/v16"],
            "routes": {
                "/stockland/v1/app/bootstrap": {"endpoints": [{"methods": ["GET"]}]},
                "/stockland/v16/system/ping": {"endpoints": [{"methods": ["GET"]}]},
            },
        }
        with self.assertRaisesRegex(ValueError, "missing registered route /stockland/v16/setup/status"):
            validate_index(document)

    def test_agent_instructions_match_candidate_route_contract(self) -> None:
        repository_root = Path(__file__).resolve().parents[2]
        instructions = (repository_root / "AGENTS.md").read_text(encoding="utf-8")
        for namespace, required_routes in EXPECTED_ROUTES.items():
            self.assertIn(f"`{namespace}`", instructions)
            for route, methods in required_routes.items():
                self.assertIn(f"`{route}`", instructions)
                for method in methods:
                    self.assertIn(f"[{method}]", instructions)
        self.assertNotIn("`stockland/v1` (`/stockland/v1/app/bootstrap`)", instructions)
        self.assertNotIn("`stockland/v16/system/ping`", instructions)

    def test_loads_utf8_bom_rest_index_json(self) -> None:
        with tempfile.NamedTemporaryFile("wb") as body:
            body.write(bytes.fromhex("efbbbf") + json.dumps(valid_index()).encode("utf-8"))
            body.flush()
            with patch("sys.argv", ["check_rest_index.py", body.name]):
                self.assertEqual(len(validate_index(load_document())), 3)

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
