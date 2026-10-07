"""Laya diagnostic: the most explicit 'conclusion in words' state, in English, on both checkpoints.
If Laya cannot separate "Missing: none" from "Missing: Intune/MDM", the problem is the task, not Danish."""
import json, time, statistics, sys
from pathlib import Path
import laya
sys.path.insert(0, str(Path(__file__).parent))
from run_laya import Q, auc, spearman, data

def state(job, p):
    miss = [req for req, _, ok in p["checks"] if not ok]
    met = len(p["checks"]) - len(miss)
    return (f"Job: {job['title']}. The candidate meets {met} of {len(p['checks'])} required qualifications. "
            f"Missing requirements: {', '.join(miss) if miss else 'none'}.")

res = []
for name in ("multilingual", "english"):
    repo, sub = laya.DEFAULT_MODELS[name]
    agent = laya.load(repo, subfolder=sub, device="cpu")
    rows = []
    for p in data["pairs"]:
        t = time.perf_counter()
        r = agent.predict(state(data["jobs"][p["job"]], p), Q["en"])["answers"]
        ms = (time.perf_counter() - t) * 1000
        rows.append({"id": p["id"], "truth": p["truth"], "p_meets": r["meets"]["noul"], "decision": r["decision"]["choice"],
                     "fit_ev": r["fit"]["score"], "ms": ms})
    pos = [r["p_meets"] for r in rows if r["truth"]["meets"]]; neg = [r["p_meets"] for r in rows if not r["truth"]["meets"]]
    s = {"checkpoint": name, "style": "summary_en", "meets_auc": round(auc(pos, neg), 3),
         "meets_acc@0.5": sum((r["p_meets"] >= 0.5) == r["truth"]["meets"] for r in rows) / 20,
         "p_meets_true_mean": round(statistics.mean(pos), 3), "p_meets_false_mean": round(statistics.mean(neg), 3),
         "fit_spearman": round(spearman([r["fit_ev"] for r in rows], [r["truth"]["fit"] for r in rows]), 3),
         "decision_acc": sum(r["decision"] == r["truth"]["decision"] for r in rows) / 20,
         "ms_median": round(statistics.median(r["ms"] for r in rows))}
    print(json.dumps(s)); res.append(s)
    (Path(__file__).parent.parent / "results" / f"laya_summary_en_{name}.jsonl").write_text(chr(10).join(json.dumps(r) for r in rows), encoding="utf8")
out = Path(__file__).parent.parent / "results" / "laya_summary.json"
d = json.loads(out.read_text(encoding="utf8")); d["diagnostic_summary_en"] = res
out.write_text(json.dumps(d, indent=1, ensure_ascii=False), encoding="utf8")
