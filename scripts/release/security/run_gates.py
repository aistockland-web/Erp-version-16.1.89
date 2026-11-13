#!/usr/bin/env python3
"""CAP-09 / CAP-13 gates over the exact plugin source: PHP lint, PHP 8.3-8.4 compatibility,
secret scan, Semgrep, WordPress security sniffs. Fails on any finding not in accepted_findings.json.
Usage: run_gates.py <plugin-dir> <tools-dir>   (tools-dir holds PHP_CodeSniffer, WPCS, semgrep-rules clones)"""
import json, os, re, subprocess, sys, collections
plugin, tools = os.path.abspath(sys.argv[1]), os.path.abspath(sys.argv[2])
here = os.path.dirname(os.path.abspath(__file__))
acc = json.load(open(os.path.join(here, "accepted_findings.json")))
fails, notes = [], []
def run(cmd, **kw): return subprocess.run(cmd, capture_output=True, text=True, **kw)
def rel(p): return os.path.relpath(p, plugin)

# 1. PHP lint on the running PHP version
php = run(["php", "-r", "echo PHP_VERSION;"]).stdout
bad = 0
for root, _, files in os.walk(plugin):
    for f in files:
        if f.endswith(".php"):
            r = run(["php", "-l", os.path.join(root, f)])
            if r.returncode: bad += 1; fails.append("LINT " + rel(os.path.join(root, f)) + ": " + r.stdout.strip()[:160])
notes.append(f"PHP {php} lint: {'PASS' if not bad else 'FAIL'}")

phpcs = ["php", os.path.join(tools, "PHP_CodeSniffer/bin/phpcs")]
paths = ",".join(os.path.join(tools, d) for d in ["WordPress-Coding-Standards","PHPCSUtils","PHPCSExtra","PHPCompatibility","PHPCompatibilityWP","PHPCompatibilityParagonie"])
run(phpcs + ["--config-set", "installed_paths", paths])

# 2. PHP 8.3-8.4 compatibility
r = run(phpcs + ["--standard=PHPCompatibilityWP", "--runtime-set", "testVersion", "8.3-8.4", "--extensions=php", "-q", "--report=json", plugin])
data = json.loads(r.stdout or "{}"); n = data.get("totals", {}).get("errors", 0) + data.get("totals", {}).get("warnings", 0)
notes.append(f"PHP 8.3-8.4 compatibility findings: {n}")
if n: fails.append(f"PHP 8.3-8.4 compatibility: {n} finding(s)")

# 3. secret scan
pat = re.compile(r"(AKIA[0-9A-Z]{16}|-----BEGIN (RSA |EC |OPENSSH )?PRIVATE KEY-----|ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{30,}|xox[baprs]-[A-Za-z0-9-]{10,}|sk-[A-Za-z0-9]{32,}|AIza[0-9A-Za-z_-]{35})")
hits = 0
for root, _, files in os.walk(plugin):
    for f in files:
        try: t = open(os.path.join(root, f), errors="ignore").read()
        except OSError: continue
        if pat.search(t): hits += 1; fails.append("SECRET pattern in " + rel(os.path.join(root, f)))
notes.append(f"secret scan: {'PASS' if not hits else 'FAIL'}")

# 4. WordPress security sniffs
sn = "WordPress.Security.EscapeOutput,WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput,WordPress.DB.PreparedSQL,WordPress.DB.PreparedSQLPlaceholders,WordPress.PHP.DiscouragedPHPFunctions,WordPress.PHP.DevelopmentFunctions"
r = run(phpcs + ["--standard=WordPress", "--sniffs=" + sn, "--extensions=php", "-q", "--report=json", plugin])
data = json.loads(r.stdout or "{}"); found = collections.Counter(); esc = 0
for path, info in data.get("files", {}).items():
    for m in info["messages"]:
        s = m["source"]
        if s.startswith("WordPress.Security.EscapeOutput"): esc += 1; continue
        found[(rel(path), s)] += 1
allowed = collections.Counter()
for a in acc["wpcs"]: allowed[(a["file"], a["sniff"])] += a["max"]
for (f, s), c in found.items():
    cap = sum(v for (af, as_), v in allowed.items() if af == f and (s == as_ or s.startswith(as_ + ".")))
    if c > cap: fails.append(f"WPCS new finding {f} {s} x{c} (accepted {cap})")
if esc > acc["wpcs_escape_output_max_total"]: fails.append(f"WPCS EscapeOutput {esc} > accepted {acc['wpcs_escape_output_max_total']}")
notes.append(f"WPCS security findings: {sum(found.values())} + {esc} escape-output (all within reviewed allowlist unless listed below)")

# 5. Semgrep
sg = run(["semgrep", "--config", os.path.join(tools, "semgrep-rules/php/lang"), "--config", os.path.join(tools, "semgrep-rules/php/wordpress-plugins"),
          "--metrics=off", "--disable-version-check", "--json", plugin])
try: res = json.loads(sg.stdout).get("results", [])
except Exception: res = None; fails.append("semgrep did not produce JSON: " + sg.stderr[-200:])
if res is not None:
    c = collections.Counter((rel(x["path"]), x["check_id"].split(".")[-1]) for x in res)
    for (f, rule), n in c.items():
        cap = sum(a["max"] for a in acc["semgrep"] if a["file"] == f and a["rule"] == rule)
        if n > cap: fails.append(f"SEMGREP new finding {f} {rule} x{n} (accepted {cap})")
    notes.append(f"semgrep findings: {len(res)} (reviewed allowlist: {sum(a['max'] for a in acc['semgrep'])})")

print("\n".join(notes))
print("\n".join("FAIL " + f for f in fails) if fails else "RESULT PASS")
sys.exit(1 if fails else 0)
