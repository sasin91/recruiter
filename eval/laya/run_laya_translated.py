"""Translate-then-Laya: the raw job + candidate profile in English (hand-translated) on both checkpoints.
Compare with run_laya_summary.py, where code first decides each requirement."""
import json, time, statistics, sys
from pathlib import Path
import laya
sys.path.insert(0, str(Path(__file__).parent))
from run_laya import Q, auc, spearman, data

en = json.loads((Path(__file__).parent / "pairs_en.json").read_text(encoding="utf8"))

def state(job, cand):
    must = "\n".join(f"- {m}" for m in job["must"]); nice = "\n".join(f"- {m}" for m in job["nice"]) or "- (none)"
    return f"Job: {job['title']}\nRequirements:\n{must}\nNice to have:\n{nice}\n\nCandidate:\n{cand}"

res = []
for name in ("english", "multilingual"):
    repo, sub = laya.DEFAULT_MODELS[name]
    agent = laya.load(repo, subfolder=sub, device="cpu")
    rows = []
    for p in data["pairs"]:
        t = time.perf_counter()
        a = agent.predict(state(en["jobs"][p["job"]], en["cands"][str(p["id"])]), Q["en"])["answers"]
        rows.append({"id": p["id"], "truth": p["truth"], "p_meets": a["meets"]["noul"], "fit": a["fit"]["score"],
                     "decision": a["decision"]["choice"], "ms": (time.perf_counter() - t) * 1000})
    pos = [r["p_meets"] for r in rows if r["truth"]["meets"]]; neg = [r["p_meets"] for r in rows if not r["truth"]["meets"]]
    s = {"checkpoint": name, "style": "raw_translated_en", "meets_auc": round(auc(pos, neg), 3),
         "meets_acc@0.5": sum((r["p_meets"] >= 0.5) == r["truth"]["meets"] for r in rows) / 20,
         "p_meets_true_mean": round(statistics.mean(pos), 3), "p_meets_false_mean": round(statistics.mean(neg), 3),
         "fit_spearman": round(spearman([r["fit"] for r in rows], [r["truth"]["fit"] for r in rows]), 3),
         "decision_acc": sum(r["decision"] == r["truth"]["decision"] for r in rows) / 20,
         "ms_median": round(statistics.median(r["ms"] for r in rows))}
    print(json.dumps(s)); res.append(s)
    (Path(__file__).parent.parent / "results" / f"laya_translated_{name}.jsonl").write_text(chr(10).join(json.dumps(r) for r in rows), encoding="utf8")
out = Path(__file__).parent.parent / "results" / "laya_summary.json"
d = json.loads(out.read_text(encoding="utf8")); d["raw_translated_en"] = res
out.write_text(json.dumps(d, indent=1, ensure_ascii=False), encoding="utf8")
