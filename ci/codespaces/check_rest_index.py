#!/usr/bin/env python3
"""Fail-closed smoke check for the Stockland REST contract in a WP REST index."""
from __future__ import annotations

import json
import sys
from collections.abc import Mapping
from typing import Any

EXPECTED_ROUTES = {
    "stockland/v1": ("/stockland/v1/app/bootstrap",),
    "stockland/v16": ("/stockland/v16/system/ping",),
}


def validate_index(document: Mapping[str, Any]) -> list[str]:
    namespaces = document.get("namespaces")
    routes = document.get("routes")
    if not isinstance(namespaces, list):
        raise ValueError("WordPress REST index has no namespaces array")
    if not isinstance(routes, dict):
        raise ValueError("WordPress REST index has no routes object")

    namespace_set = set(item for item in namespaces if isinstance(item, str))
    errors: list[str] = []
    for namespace, required_routes in EXPECTED_ROUTES.items():
        if namespace not in namespace_set:
            errors.append(f"missing namespace {namespace}")
            continue
        for route in required_routes:
            route_data = routes.get(route)
            if not isinstance(route_data, dict):
                errors.append(f"missing registered route {route}")
                continue
            endpoints = route_data.get("endpoints")
            if not isinstance(endpoints, list) or not endpoints:
                errors.append(f"route {route} has no endpoint definitions")
                continue
            methods = {
                method.upper()
                for endpoint in endpoints
                if isinstance(endpoint, dict)
                for method in (
                    endpoint.get("methods", [])
                    if isinstance(endpoint.get("methods", []), list)
                    else [endpoint.get("methods")]
                )
                if isinstance(method, str)
            }
            if not methods:
                errors.append(f"route {route} does not advertise HTTP methods")
    if errors:
        raise ValueError("; ".join(errors))
    return [
        f"PASS {namespace}: " + ", ".join(required_routes)
        for namespace, required_routes in EXPECTED_ROUTES.items()
    ]


def main() -> int:
    try:
        document = json.load(sys.stdin)
        for message in validate_index(document):
            print(message)
        print("PASS Stockland REST namespace and route contract")
        return 0
    except (json.JSONDecodeError, ValueError, TypeError) as exc:
        print(f"FAIL Stockland REST contract: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
