#!/usr/bin/env python3
"""
Stockland branch POS folder agent.

This agent only uploads completed daily POS files to the ERP's Daily POS preview
endpoint. It never commits sales, changes stock, or releases accounting entries.
Use a dedicated Stockland Sales Employee or Branch Manager account assigned to
the one branch configured below.
"""
from __future__ import annotations

import argparse
import base64
import hashlib
import json
import os
import re
import shutil
import sqlite3
import sys
import time
import urllib.error
import urllib.request
from datetime import datetime
from pathlib import Path
from typing import Any
import ssl
from urllib.parse import urlsplit


ALLOWED_SUFFIXES = {".xlsx", ".csv"}
DEFAULT_MAX_BYTES = 8 * 1024 * 1024
DATE_IN_NAME = re.compile(r"(?<!\d)(20\d{2}-\d{2}-\d{2})(?!\d)")


class AgentError(RuntimeError):
    """Configuration or upload failure safe to show in the local agent log."""


def required_env(name: str) -> str:
    value = os.environ.get(name, "").strip()
    if not value:
        raise AgentError(f"Missing required environment variable: {name}")
    return value


def validate_business_date(value: str) -> str:
    try:
        return datetime.strptime(value, "%Y-%m-%d").date().isoformat()
    except ValueError as exc:
        raise AgentError("Business date must use YYYY-MM-DD.") from exc


def business_date_for_file(path: Path, fallback: str | None = None) -> str:
    match = DATE_IN_NAME.search(path.name)
    candidate = match.group(1) if match else (fallback or "")
    if not candidate:
        raise AgentError(
            f"No YYYY-MM-DD date found in '{path.name}'. Add the date to the "
            "filename or set STOCKLAND_POS_BUSINESS_DATE."
        )
    return validate_business_date(candidate)


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def is_stable_file(path: Path, stable_seconds: int, now: float | None = None) -> bool:
    try:
        stat = path.stat()
    except OSError:
        return False
    clock = time.time() if now is None else now
    return path.is_file() and not path.is_symlink() and stat.st_size > 0 and clock - stat.st_mtime >= stable_seconds


def load_config() -> dict[str, Any]:
    base_url_raw = required_env("STOCKLAND_ERP_URL")
    try:
        parsed_base_url = urlsplit(base_url_raw)
        hostname = parsed_base_url.hostname
        # Force port parsing too; urlsplit otherwise leaves malformed ports unchecked.
        _port = parsed_base_url.port
    except ValueError as exc:
        raise AgentError("STOCKLAND_ERP_URL is not a valid HTTPS base URL.") from exc
    if (
        base_url_raw != base_url_raw.strip()
        or parsed_base_url.scheme.lower() != "https"
        or not hostname
        or parsed_base_url.username is not None
        or parsed_base_url.password is not None
        or parsed_base_url.query
        or parsed_base_url.fragment
    ):
        raise AgentError(
            "STOCKLAND_ERP_URL must be an HTTPS base URL without embedded credentials, query or fragment."
        )
    base_url = base_url_raw.rstrip("/")
    username = required_env("STOCKLAND_ERP_USERNAME")
    password = required_env("STOCKLAND_ERP_APP_PASSWORD")
    branch_id = required_env("STOCKLAND_POS_BRANCH_ID")
    if not branch_id.isdigit() or int(branch_id) <= 0:
        raise AgentError("STOCKLAND_POS_BRANCH_ID must be a positive numeric branch ID.")

    inbox = Path(required_env("STOCKLAND_POS_INBOX")).expanduser().resolve()
    if not inbox.exists() or not inbox.is_dir():
        raise AgentError(f"POS inbox directory does not exist: {inbox}")

    processed_text = os.environ.get("STOCKLAND_POS_PROCESSED", "").strip()
    processed = Path(processed_text).expanduser().resolve() if processed_text else inbox / "processed"
    if processed == inbox or inbox in processed.parents:
        raise AgentError("The processed directory must be outside the watched inbox.")
    processed.mkdir(parents=True, exist_ok=True)

    state_text = os.environ.get("STOCKLAND_POS_STATE_DB", "").strip()
    state_db = Path(state_text).expanduser().resolve() if state_text else processed / "stockland-pos-agent.sqlite3"
    stable_seconds = int(os.environ.get("STOCKLAND_POS_STABLE_SECONDS", "60"))
    max_bytes = int(os.environ.get("STOCKLAND_POS_MAX_BYTES", str(DEFAULT_MAX_BYTES)))
    fallback_date = os.environ.get("STOCKLAND_POS_BUSINESS_DATE", "").strip() or None
    if stable_seconds < 0 or max_bytes <= 0 or max_bytes > DEFAULT_MAX_BYTES:
        raise AgentError("Stable seconds or max upload bytes is outside the supported safe range.")

    return {
        "base_url": base_url,
        "username": username,
        "password": password,
        "branch_id": int(branch_id),
        "inbox": inbox,
        "processed": processed,
        "state_db": state_db,
        "stable_seconds": stable_seconds,
        "max_bytes": max_bytes,
        "fallback_date": fallback_date,
        "endpoint": base_url + "/wp-json/stockland/v16/import/daily-sales/preview",
    }


def open_state(path: Path, branch_id: int) -> sqlite3.Connection:
    path.parent.mkdir(parents=True, exist_ok=True)
    db = sqlite3.connect(str(path), timeout=30)
    # Keep the original table as a compatibility source and migrate its history
    # into a correctly scoped key. A content hash alone is not a daily import key:
    # identical bytes can legitimately be used for another branch or business date.
    db.execute(
        """
        CREATE TABLE IF NOT EXISTS uploaded_sources (
            sha256 TEXT PRIMARY KEY,
            original_name TEXT NOT NULL,
            business_date TEXT NOT NULL,
            job_id INTEGER,
            job_status TEXT NOT NULL,
            uploaded_at TEXT NOT NULL,
            moved_path TEXT
        )
        """
    )
    db.execute(
        """
        CREATE TABLE IF NOT EXISTS uploaded_sources_scoped (
            branch_id INTEGER NOT NULL,
            business_date TEXT NOT NULL,
            sha256 TEXT NOT NULL,
            original_name TEXT NOT NULL,
            job_id INTEGER,
            job_status TEXT NOT NULL,
            uploaded_at TEXT NOT NULL,
            moved_path TEXT,
            PRIMARY KEY (branch_id, business_date, sha256)
        )
        """
    )
    columns = {row[1] for row in db.execute("PRAGMA table_info(uploaded_sources)")}
    legacy_columns = {
        "sha256", "original_name", "business_date", "job_id",
        "job_status", "uploaded_at", "moved_path",
    }
    if legacy_columns.issubset(columns):
        db.execute(
            """
            INSERT OR IGNORE INTO uploaded_sources_scoped
                (branch_id, business_date, sha256, original_name, job_id,
                 job_status, uploaded_at, moved_path)
            SELECT ?, business_date, sha256, original_name, job_id,
                   job_status, uploaded_at, moved_path
            FROM uploaded_sources
            """,
            (int(branch_id),),
        )
    db.commit()
    return db


def was_uploaded(
    db: sqlite3.Connection, branch_id: int, business_date: str, source_hash: str
) -> bool:
    row = db.execute(
        """
        SELECT 1 FROM uploaded_sources_scoped
        WHERE branch_id = ? AND business_date = ? AND sha256 = ?
        LIMIT 1
        """,
        (int(branch_id), business_date, source_hash),
    ).fetchone()
    return row is not None


def multipart_body(fields: dict[str, str], field_name: str, filename: str, data: bytes) -> tuple[bytes, str]:
    boundary = "----StocklandPOS" + hashlib.sha256(os.urandom(32)).hexdigest()[:24]
    chunks: list[bytes] = []
    for name, value in fields.items():
        chunks.extend(
            [
                f"--{boundary}\r\n".encode(),
                f'Content-Disposition: form-data; name="{name}"\r\n\r\n'.encode(),
                value.encode("utf-8"),
                b"\r\n",
            ]
        )
    safe_name = Path(filename).name.replace('"', "")
    suffix = Path(safe_name).suffix.lower()
    mime = (
        "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
        if suffix == ".xlsx"
        else "text/csv; charset=utf-8"
    )
    chunks.extend(
        [
            f"--{boundary}\r\n".encode(),
            f'Content-Disposition: form-data; name="{field_name}"; filename="{safe_name}"\r\n'.encode(),
            f"Content-Type: {mime}\r\n\r\n".encode(),
            data,
            b"\r\n",
            f"--{boundary}--\r\n".encode(),
        ]
    )
    return b"".join(chunks), boundary


def upload_file(config: dict[str, Any], path: Path, business_date: str) -> dict[str, Any]:
    data = path.read_bytes()
    fields = {
        "branch_location_id": str(config["branch_id"]),
        "business_date": business_date,
    }
    body, boundary = multipart_body(fields, "file", path.name, data)
    credentials = f"{config['username']}:{config['password']}".encode("utf-8")
    authorization = base64.b64encode(credentials).decode("ascii")
    request = urllib.request.Request(
        config["endpoint"],
        data=body,
        method="POST",
        headers={
            "Authorization": "Basic " + authorization,
            "Content-Type": "multipart/form-data; boundary=" + boundary,
            "Accept": "application/json",
            "User-Agent": "Stockland-POS-Folder-Agent/1.0",
        },
    )
    context = ssl.create_default_context()
    try:
        with urllib.request.urlopen(request, timeout=90, context=context) as response:
            raw = response.read(2 * 1024 * 1024)
            result = json.loads(raw.decode("utf-8"))
    except urllib.error.HTTPError as exc:
        detail = exc.read(16 * 1024).decode("utf-8", errors="replace")
        raise AgentError(f"ERP returned HTTP {exc.code}: {detail}") from exc
    except urllib.error.URLError as exc:
        raise AgentError(f"Could not reach the ERP securely: {exc.reason}") from exc
    except (TimeoutError, json.JSONDecodeError) as exc:
        raise AgentError(f"ERP response was incomplete or invalid: {exc}") from exc

    if not isinstance(result, dict) or not result.get("job_id"):
        raise AgentError("ERP did not return a staged import job ID; source file was left in the inbox.")
    status = str(result.get("status", "")).upper()
    if status not in {"READY", "REVIEW"}:
        raise AgentError(f"ERP returned unexpected staging status '{status}'; source file was left in the inbox.")
    return result


def process_once(config: dict[str, Any], dry_run: bool = False) -> int:
    db = open_state(config["state_db"], config["branch_id"])
    failures = 0
    files = sorted(
        (
            item
            for item in config["inbox"].iterdir()
            if item.is_file() and not item.is_symlink() and item.suffix.lower() in ALLOWED_SUFFIXES
        ),
        key=lambda item: (item.stat().st_mtime, item.name.casefold()),
    )
    if not files:
        print("No new POS files found.")
        return 0

    for path in files:
        try:
            if not is_stable_file(path, config["stable_seconds"]):
                print(f"WAIT: {path.name} is not yet stable.")
                continue
            size = path.stat().st_size
            if size <= 0 or size > config["max_bytes"]:
                raise AgentError(f"File size {size} bytes is outside the upload limit.")
            business_date = business_date_for_file(path, config["fallback_date"])
            source_hash = sha256_file(path)
            if was_uploaded(db, config["branch_id"], business_date, source_hash):
                print(f"SKIP: {path.name} was already staged by this agent.")
                continue
            if dry_run:
                print(f"READY: {path.name} ({size} bytes), date {business_date}, SHA-256 {source_hash}")
                continue

            result = upload_file(config, path, business_date)
            job_id = int(result["job_id"])
            status = str(result["status"]).upper()
            destination = config["processed"] / path.name
            if destination.exists():
                destination = config["processed"] / f"{path.stem}-{source_hash[:10]}{path.suffix}"
            shutil.move(str(path), str(destination))
            db.execute(
                """
                INSERT INTO uploaded_sources_scoped
                    (branch_id, business_date, sha256, original_name, job_id, job_status, uploaded_at, moved_path)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                """,
                (
                    config["branch_id"],
                    business_date,
                    source_hash,
                    path.name,
                    job_id,
                    status,
                    datetime.now().astimezone().isoformat(timespec="seconds"),
                    str(destination),
                ),
            )
            db.commit()
            print(f"STAGED: {path.name} -> ERP job #{job_id} ({status}); moved to {destination}")
            print("CONTROL: no sale, inventory, VAT ledger or GL entry was released by this agent.")
        except (OSError, AgentError, ValueError) as exc:
            failures += 1
            print(f"ERROR: {path.name}: {exc}", file=sys.stderr)
    return 1 if failures else 0


def main() -> int:
    parser = argparse.ArgumentParser(description="Upload stable branch POS exports for ERP preview/staging only.")
    parser.add_argument("--dry-run", action="store_true", help="List eligible files without contacting the ERP.")
    args = parser.parse_args()
    try:
        config = load_config()
        return process_once(config, dry_run=args.dry_run)
    except AgentError as exc:
        print(f"CONFIGURATION ERROR: {exc}", file=sys.stderr)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
