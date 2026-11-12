#!/usr/bin/env python3
"""Fail-closed smoke check for the Stockland .13/.15 WordPress REST contract."""
from __future__ import annotations

import json
import sys
from collections.abc import Mapping
from pathlib import Path
from typing import Any

# This checker runs against the locked 16.2.13 and repaired 16.2.15 candidate
# archives. Keep this legacy candidate contract distinct from the 16.2.18
# master's separate pre-schema setup routes.
EXPECTED_ROUTES = {
    "stockland/v1": {
        "/stockland/v1/app/bootstrap": {"GET"},
    },
    "stockland/v16": {
        "/stockland/v16/system/ping": {"GET"},
    },
}


def validate_index(document: Mapping[str, Any]) -> list[str]:
    """Validate both Stockland namespaces, exact route keys and HTTP methods."""
    namespaces = document.get("namespaces")
    routes = document.get("routes")
    if not isinstance(namespaces, list):
        raise ValueError("WordPress REST index has no namespaces array")
    if not isinstance(routes, dict):
        raise ValueError("WordPress REST index has no routes object")

    namespace_set = {item for item in namespaces if isinstance(item, str)}
    errors: list[str] = []
    passed: list[str] = []

    for namespace, required_routes in EXPECTED_ROUTES.items():
        if namespace not in namespace_set:
            errors.append(f"missing namespace {namespace}")
            continue

        for route, expected_methods in required_routes.items():
            route_data = routes.get(route)
            if not isinstance(route_data, dict):
                errors.append(f"missing registered route {route}")
                continue

            endpoints = route_data.get("endpoints")
            if not isinstance(endpoints, list) or not endpoints:
                errors.append(f"route {route} has no endpoint definitions")
                continue

            methods: set[str] = set()
            for endpoint in endpoints:
                if not isinstance(endpoint, Mapping):
                    continue
                advertised = endpoint.get("methods", [])
                if isinstance(advertised, str):
                    advertised = [advertised]
                if isinstance(advertised, list):
                    methods.update(
                        method.strip().upper()
                        for method in advertised
                        if isinstance(method, str) and method.strip()
                    )

            missing_methods = expected_methods - methods
            if missing_methods:
                errors.append(
                    f"route {route} is missing expected methods {sorted(missing_methods)}"
                )
            else:
                passed.append(
                    f"PASS {namespace}: {route} [{'/'.join(sorted(expected_methods))}]"
                )

    if errors:
        raise ValueError("; ".join(errors))
    return passed


def load_document() -> Mapping[str, Any]:
    if len(sys.argv) > 2:
        raise ValueError("usage: check_rest_index.py [response-body.json]")
    if len(sys.argv) == 2:
        payload = Path(sys.argv[1]).read_bytes()
    else:
        payload = sys.stdin.buffer.read()
    if not payload:
        raise ValueError("WordPress REST index response body is empty")
    text = payload.decode("utf-8-sig", errors="replace").strip()
    try:
        document = json.loads(text)
    except json.JSONDecodeError as exc:
        preview = " ".join(text[:240].split())
        raise ValueError(
            f"REST response is not JSON ({exc}); response preview={preview!r}"
        ) from exc
    if not isinstance(document, Mapping):
        raise ValueError("WordPress REST index JSON must be an object")
    return document


def main() -> int:
    try:
        document = load_document()
        for message in validate_index(document):
            print(message)
        print("PASS Stockland .13/.15 REST namespace and route contract")
        return 0
    except (OSError, UnicodeDecodeError, ValueError, TypeError) as exc:
        print(f"FAIL Stockland REST contract: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
