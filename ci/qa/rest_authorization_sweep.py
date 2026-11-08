"""Unauthenticated + subscriber REST allow/deny sweep for the Stockland plugin.
Run ONLY against a disposable local WordPress. Usage:
  SWEEP_ADMIN_USER=.. SWEEP_ADMIN_PASS=.. SWEEP_SUB_USER=.. SWEEP_SUB_PASS=.. python3 -I rest_authorization_sweep.py <port>
Path parameters are filled with "1"; routes needing other tokens (entity names, SOP codes) must be checked separately.
"""
import json,os,re,subprocess,sys,collections
PORT=sys.argv[1]; B=f"http://localhost:{PORT}"
def curl(args): return subprocess.run(["curl","-s","--noproxy","localhost","-m","30"]+args,capture_output=True,text=True).stdout
def login(user,pwd,jar):
    open(jar,'w').close()
    curl(["-c",jar,"-b",jar,"-o","/dev/null",f"{B}/wp-login.php"])
    curl(["-c",jar,"-b",jar,"-o","/dev/null","-H","Cookie: wordpress_test_cookie=WP%20Cookie%20check","--data-urlencode",f"log={user}","--data-urlencode",f"pwd={pwd}","-d","wp-submit=Log+In&testcookie=1",f"{B}/wp-login.php"])
    return curl(["-b",jar,f"{B}/wp-admin/admin-ajax.php?action=rest-nonce"]).strip()
adm_nonce=login(os.environ["SWEEP_ADMIN_USER"],os.environ["SWEEP_ADMIN_PASS"],"/tmp/sweep_jar_adm")
idx=json.loads(curl(["-b","/tmp/sweep_jar_adm","-H",f"X-WP-Nonce: {adm_nonce}",f"{B}/?rest_route=/stockland/v16"]))
routes=[]
for r,d in idx["routes"].items():
    if r=="/stockland/v16": continue
    ms=set()
    for e in d["endpoints"]: ms|=set(e["methods"])
    path=re.sub(r"\(\?P<[^>]+>[^)]*\)","1",r)
    for m in sorted(ms): routes.append((m,path))
sub_nonce=login(os.environ["SWEEP_SUB_USER"],os.environ["SWEEP_SUB_PASS"],"/tmp/sweep_jar_sub")
print("routes x methods:",len(routes),"| sub nonce ok:",sub_nonce not in("","0"))
def call(m,path,jar=None,nonce=None):
    a=["-o","/dev/null","-w","%{http_code}","-X",m,"-H","Content-Type: application/json","-d","{}"]
    if jar: a+=["-b",jar,"-H",f"X-WP-Nonce: {nonce}"]
    return curl(a+[f"{B}/?rest_route={path}"])
res=[];bad=[]
for m,p in routes:
    u=call(m,p); s=call(m,p,"/tmp/sweep_jar_sub",sub_nonce)
    res.append((m,p,u,s))
    if not (u.startswith("40") and s.startswith("40")): bad.append((m,p,u,s))
cnt=collections.Counter((u,s) for _,_,u,s in res)
print("status pairs (unauth, subscriber):",dict(cnt))
print("NOT DENIED for unauth or subscriber:",len(bad))
for b in bad: print("  ",*b)
json.dump(res,open(f"/tmp/authsweep_{PORT}.json","w"))
