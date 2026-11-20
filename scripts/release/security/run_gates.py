#!/usr/bin/env python3
"""CAP-09 / CAP-13 gates over the exact plugin source.

Checks PHP lint, PHP 8.3-8.4 compatibility, secrets, Semgrep, WordPress security sniffs,
and CSS bundle consistency. Every scanner must emit a valid report; scanner/tool failures
are release-blocking and must never be interpreted as zero findings.

Usage: run_gates.py <plugin-dir> <tools-dir>
"""
import collections
import json
import os
import re
import subprocess
import sys

if len(sys.argv) != 3:
    print("Usage: run_gates.py <plugin-dir> <tools-dir>", file=sys.stderr)
    sys.exit(2)

plugin, tools = (os.path.abspath(arg) for arg in sys.argv[1:])
here = os.path.dirname(os.path.abspath(__file__))
accepted_path = os.path.join(here, "accepted_findings.json")
fails, notes = [], []

if not os.path.isdir(plugin):
    print("Plugin source directory does not exist: " + plugin, file=sys.stderr)
    sys.exit(2)
if not os.path.isfile(accepted_path):
    print("Security allowlist is missing: " + accepted_path, file=sys.stderr)
    sys.exit(2)

try:
    with open(accepted_path, encoding="utf-8") as handle:
        acc = json.load(handle)
except (OSError, json.JSONDecodeError) as exc:
    print("Security allowlist could not be read: " + str(exc), file=sys.stderr)
    sys.exit(2)

def run(cmd, **kw):
    """Run a tool and convert an OS launch error into a checked nonzero result."""
    try:
        return subprocess.run(cmd, capture_output=True, text=True, **kw)
    except OSError as exc:
        return subprocess.CompletedProcess(cmd, 127, "", str(exc))

def rel(path):
    return os.path.relpath(path, plugin)

def json_report(result, label, required_keys):
    """Require valid machine-readable output; an empty/failed scanner is not a pass."""
    if not result.stdout.strip():
        fails.append(label + " produced no JSON report (exit " + str(result.returncode) + "): " + result.stderr[-240:])
        return {}
    try:
        data = json.loads(result.stdout)
    except json.JSONDecodeError as exc:
        fails.append(label + " produced invalid JSON: " + str(exc) + "; " + result.stderr[-240:])
        return {}
    if not isinstance(data, dict) or any(key not in data for key in required_keys):
        fails.append(label + " JSON report is missing required fields: " + ", ".join(sorted(required_keys)))
        return {}
    return data

# 1. PHP syntax on the selected runtime. An empty source tree is an error.
php_files = []
for root, _, files in os.walk(plugin):
    php_files.extend(os.path.join(root, name) for name in files if name.endswith(".php"))
if not php_files:
    fails.append("No PHP source files found under the requested plugin directory")
bad = 0
php_version = run(["php", "-r", "echo PHP_VERSION;"])
if php_version.returncode:
    fails.append("Could not determine the PHP runtime: " + php_version.stderr[-240:])
php = php_version.stdout.strip() or "UNKNOWN"
for path in php_files:
    result = run(["php", "-l", path])
    if result.returncode:
        bad += 1
        fails.append("LINT " + rel(path) + ": " + (result.stdout + result.stderr).strip()[:240])
notes.append(f"PHP {php} lint: {'PASS' if not bad else 'FAIL'} ({len(php_files)} files)")

phpcs = ["php", os.path.join(tools, "PHP_CodeSniffer/bin/phpcs")]
installed_paths = ",".join(
    os.path.join(tools, name)
    for name in [
        "WordPress-Coding-Standards", "PHPCSUtils", "PHPCSExtra",
        "PHPCompatibility", "PHPCompatibilityWP", "PHPCompatibilityParagonie",
    ]
)
config_result = run(phpcs + ["--config-set", "installed_paths", installed_paths])
if config_result.returncode:
    fails.append("PHP_CodeSniffer installed_paths configuration failed: " + str(config_result.returncode) + " " + config_result.stderr[-240:])

# 2. PHP 8.3-8.4 compatibility. Findings are evaluated from the JSON report;
# a nonzero scanner exit with no reported finding indicates a tool/runtime failure.
compat = run(phpcs + [
    "--standard=PHPCompatibilityWP", "--runtime-set", "testVersion", "8.3-8.4",
    "--extensions=php", "-q", "--report=json", plugin,
])
compat_data = json_report(compat, "PHPCompatibility", {"totals", "files"})
compat_totals = compat_data.get("totals", {}) if isinstance(compat_data, dict) else {}
compat_count = int(compat_totals.get("errors", 0) or 0) + int(compat_totals.get("warnings", 0) or 0)
if compat.returncode and 0 == compat_count:
    fails.append("PHPCompatibility exited nonzero without any reported finding: " + str(compat.returncode))
if php_files and not compat_data.get("files"):
    fails.append("PHPCompatibility report contains no scanned files")
notes.append(f"PHP 8.3-8.4 compatibility findings: {compat_count}")
if compat_count:
    fails.append(f"PHP 8.3-8.4 compatibility: {compat_count} finding(s)")

# 3. Secret scan. Read errors are not silently ignored.
pat = re.compile(r"(AKIA[0-9A-Z]{16}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|xox[baprs]-[A-Za-z0-9-]{10,}|sk-[A-Za-z0-9]{32,}|AIza[0-9A-Za-z_-]{35})")
hits = 0
for root, _, files in os.walk(plugin):
    for name in files:
        path = os.path.join(root, name)
        try:
            with open(path, encoding="utf-8", errors="ignore") as handle:
                contents = handle.read()
        except OSError as exc:
            fails.append("SECRET scan could not read " + rel(path) + ": " + str(exc))
            continue
        if pat.search(contents):
            hits += 1
            fails.append("SECRET pattern in " + rel(path))
notes.append(f"secret scan: {'PASS' if not hits else 'FAIL'}")

# 4. WordPress security sniffs. Accepted, reviewed findings remain scoped by file+sniff.
sniffs = ",".join([
    "WordPress.Security.EscapeOutput", "WordPress.Security.NonceVerification",
    "WordPress.Security.ValidatedSanitizedInput", "WordPress.DB.PreparedSQL",
    "WordPress.DB.PreparedSQLPlaceholders", "WordPress.PHP.DiscouragedPHPFunctions",
    "WordPress.PHP.DevelopmentFunctions",
])
wpcs = run(phpcs + ["--standard=WordPress", "--sniffs=" + sniffs, "--extensions=php", "-q", "--report=json", plugin])
wpcs_data = json_report(wpcs, "WPCS", {"files", "totals"})
wpcs_files = wpcs_data.get("files", {}) if isinstance(wpcs_data, dict) else {}
if php_files and not wpcs_files:
    fails.append("WPCS report contains no scanned files")
found = collections.Counter()
escape_output = 0
for path, info in wpcs_files.items():
    for message in info.get("messages", []):
        source = str(message.get("source", ""))
        if source.startswith("WordPress.Security.EscapeOutput"):
            escape_output += 1
        elif source:
            found[(rel(path), source)] += 1
allowed = collections.Counter()
for item in acc.get("wpcs", []):
    allowed[(item["file"], item["sniff"])] += int(item["max"])
for (path, sniff), count in found.items():
    cap = sum(
        value for (accepted_file, accepted_sniff), value in allowed.items()
        if accepted_file == path and (sniff == accepted_sniff or sniff.startswith(accepted_sniff + "."))
    )
    if count > cap:
        fails.append(f"WPCS new finding {path} {sniff} x{count} (accepted {cap})")
escape_limit = int(acc.get("wpcs_escape_output_max_total", 0))
if escape_output > escape_limit:
    fails.append(f"WPCS EscapeOutput {escape_output} > accepted {escape_limit}")
notes.append(f"WPCS security findings: {sum(found.values())} + {escape_output} escape-output (unaccepted findings are release-blocking)")

# 5. Semgrep. A failed command, invalid JSON, or scanner error is a failure.
semgrep = run([
    "semgrep", "--config", os.path.join(tools, "semgrep-rules/php/lang"),
    "--config", os.path.join(tools, "semgrep-rules/php/wordpress-plugins"),
    "--metrics=off", "--disable-version-check", "--json", plugin,
])
try:
    semgrep_data = json.loads(semgrep.stdout) if semgrep.stdout.strip() else None
except json.JSONDecodeError as exc:
    semgrep_data = None
    fails.append("Semgrep produced invalid JSON: " + str(exc))
if not isinstance(semgrep_data, dict) or not isinstance(semgrep_data.get("results"), list) or not isinstance(semgrep_data.get("errors"), list):
    fails.append("Semgrep did not produce a complete JSON report")
    semgrep_results = []
else:
    semgrep_results = semgrep_data["results"]
    if semgrep.returncode:
        fails.append("Semgrep exited nonzero: " + str(semgrep.returncode) + " " + semgrep.stderr[-240:])
    if semgrep_data["errors"]:
        fails.append("Semgrep reported scanner errors: " + str(semgrep_data["errors"][:3])[:500])
counts = collections.Counter((rel(item["path"]), item["check_id"].split(".")[-1]) for item in semgrep_results)
for (path, rule), count in counts.items():
    cap = sum(int(item["max"]) for item in acc.get("semgrep", []) if item["file"] == path and item["rule"] == rule)
    if count > cap:
        fails.append(f"SEMGREP new finding {path} {rule} x{count} (accepted {cap})")
notes.append(f"semgrep findings: {len(semgrep_results)} (reviewed allowlist: {sum(int(item['max']) for item in acc.get('semgrep', []))})")

# 6. CSS bundle consistency: app.css must equal ordered source modules and app.min.css
# must contain every module class.
try:
    with open(os.path.join(plugin, "assets/css/modules.json"), encoding="utf-8") as handle:
        modules = json.load(handle)["modules"]
    norm = lambda text: re.sub(r"\s+", "", re.sub(r"/\*.*?\*/", "", text, flags=re.S))
    module_css = ""
    for module in modules:
        with open(os.path.join(plugin, "assets/css", module), encoding="utf-8") as handle:
            module_css += handle.read()
    with open(os.path.join(plugin, "assets/app.css"), encoding="utf-8") as handle:
        bundled_css = handle.read()
    with open(os.path.join(plugin, "assets/app.min.css"), encoding="utf-8") as handle:
        min_css = handle.read()
    missing = sorted(class_name for class_name in set(re.findall(r"\.sl-[a-z0-9-]+", module_css)) if class_name not in min_css)
    if norm(module_css) != norm(bundled_css):
        fails.append("CSS app.css does not equal the ordered modules in modules.json")
    if missing:
        fails.append("CSS app.min.css is missing classes: " + ", ".join(missing[:8]))
    notes.append("CSS bundle consistency: " + ("PASS" if norm(module_css) == norm(bundled_css) and not missing else "FAIL"))
except (OSError, KeyError, TypeError, json.JSONDecodeError) as exc:
    fails.append("CSS bundle verification could not complete: " + str(exc))

print("\n".join(notes))
print("\n".join("FAIL " + failure for failure in fails) if fails else "RESULT PASS")
sys.exit(1 if fails else 0)
