#!/usr/bin/env python3
"""Fail-closed smoke check for the Stockland pre-schema WordPress REST contract."""
from __future__ import annotations

import json
import sys
from collections.abc import Mapping
from pathlib import Path
from typing import Any

EXPECTED_ROUTES = {
    "stockland/v16": {
        "/stockland/v16/setup/status": {"GET"},
        "/stockland/v16/setup/advance": {"POST"},
        "/stockland/v16/app/access/bootstrap": {"GET"},
    },
}


def validate_index(document: Mapping[str, Any]) -> list[str]:
    namespaces = document.get("namespaces")
    routes = document.get("routes")
    if not isinstance(namespaces, list):
        raise ValueError("WordPress REST index has no namespaces array")
    if not isinstance(routes, dict):
        raise ValueError("WordPress REST index has no routes object")

    namespace_set = {item for item in namespaces if isinstance(item, str)}
    errors: list[str] = []
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
                if not isinstance(endpoint, dict):
                    continue
                advertised = endpoint.get("methods", [])
                if isinstance(advertised, str):
                    advertised = [advertised]
                if isinstance(advertised, list):
                    methods.update(
                        method.upper()
                        for method in advertised
                        if isinstance(method, str)
                    )
            missing_methods = expected_methods - methods
            if missing_methods:
                errors.append(
                    f"route {route} is missing expected methods {sorted(missing_methods)}"
                )
    if errors:
        raise ValueError("; ".join(errors))
    return [
        f"PASS {namespace}: " + ", ".join(
            f"{route} [{'/'.join(sorted(methods))}]"
            for route, methods in required_routes.items()
        )
        for namespace, required_routes in EXPECTED_ROUTES.items()
    ]


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
        print("PASS Stockland fresh-install REST namespace and route contract")
        return 0
    except (OSError, UnicodeDecodeError, ValueError, TypeError) as exc:
        print(f"FAIL Stockland REST contract: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
