"""Laya spike: multilingual checkpoint on 20 Danish job/candidate pairs.

Two state styles (raw profile vs per-requirement conclusions computed in code) x two instruction
languages (English vs Danish). Writes results/laya.jsonl and prints a summary.
Run with a venv that has `pip install laya`: python laya/run_laya.py
"""
import json, os, time, statistics
from pathlib import Path
import laya

HERE = Path(__file__).parent
data = json.loads((HERE / "pairs.json").read_text(encoding="utf8"))

Q = {
    "en": {
        "meets": {"type": "noul", "instructions": "Does the candidate meet every required qualification for the job?"},
        "fit": {"type": "score", "instructions": "How strong a fit is this candidate for the job?",
                "criteria": ["no fit", "weak fit", "partial fit", "good fit", "excellent fit"]},
        "decision": {"type": "choice", "instructions": "What should the recruiter do with this candidate?",
                     "criteria": {"interview": "invite to interview, meets the requirements",
                                  "maybe": "close, misses one requirement, worth a look",
                                  "reject": "clearly not qualified for this job"}},
    },
    "da": {
        "meets": {"type": "noul", "instructions": "Opfylder kandidaten alle jobbets krav?"},
        "fit": {"type": "score", "instructions": "Hvor godt passer kandidaten til jobbet?",
                "criteria": ["passer slet ikke", "passer dårligt", "passer delvist", "passer godt", "passer fremragende"]},
        "decision": {"type": "choice", "instructions": "Hvad skal rekruttereren gøre med kandidaten?",
                     "criteria": {"interview": "invitér til samtale, opfylder kravene",
                                  "maybe": "tæt på, mangler ét krav, værd at se på",
                                  "reject": "klart ikke kvalificeret til jobbet"}},
    },
}


def state_raw(job, p):
    must = "\n".join(f"- {m}" for m in job["must"])
    nice = "\n".join(f"- {m}" for m in job["nice"]) or "- (ingen)"
    return f"Job: {job['title']}\nKrav:\n{must}\nEn fordel:\n{nice}\n\nKandidat:\n{p['cand']}"


def state_checks(job, p):
    lines = [f"- {req}: {'OPFYLDT' if ok else 'IKKE OPFYLDT'} ({why})" for req, why, ok in p["checks"]]
    met = sum(ok for _, _, ok in p["checks"])
    return (f"Job: {job['title']}\nKandidaten opfylder {met} af {len(p['checks'])} krav.\n" + "\n".join(lines)
            + f"\n\nKandidat:\n{p['cand']}")


def auc(pos, neg):
    if not pos or not neg: return float("nan")
    return sum((a > b) + 0.5 * (a == b) for a in pos for b in neg) / (len(pos) * len(neg))


def spearman(x, y):
    def rank(v):
        s = sorted(range(len(v)), key=lambda i: v[i]); r = [0.0] * len(v); i = 0
        while i < len(s):
            j = i
            while j + 1 < len(s) and v[s[j + 1]] == v[s[i]]: j += 1
            for k in range(i, j + 1): r[s[k]] = (i + j) / 2
            i = j + 1
        return r
    rx, ry = rank(x), rank(y)
    mx, my = statistics.mean(rx), statistics.mean(ry)
    num = sum((a - mx) * (b - my) for a, b in zip(rx, ry))
    den = (sum((a - mx) ** 2 for a in rx) * sum((b - my) ** 2 for b in ry)) ** 0.5
    return num / den if den else float("nan")


def main():
    t0 = time.perf_counter()
    agent = laya.load(*laya.DEFAULT_MODELS["multilingual"][:1], subfolder=laya.DEFAULT_MODELS["multilingual"][1], device="cpu")
    load_s = time.perf_counter() - t0
    print(f"loaded multilingual in {load_s:.1f}s")
    out = open(HERE.parent / "results" / "laya.jsonl", "w", encoding="utf8")
    summary = []
    for style, fn in (("raw", state_raw), ("checks", state_checks)):
        for lang in ("en", "da"):
            rows = []
            for p in data["pairs"]:
                st = fn(data["jobs"][p["job"]], p)
                t = time.perf_counter()
                r = agent.predict(st, Q[lang])
                ms = (time.perf_counter() - t) * 1000
                a = r["answers"]
                fit_probs = a["fit"].get("probs") or a["fit"].get("probabilities")
                row = {"style": style, "lang": lang, "id": p["id"], "ms": round(ms), "truth": p["truth"],
                       "p_meets": a["meets"]["noul"], "fit": a["fit"].get("score", a["fit"].get("level")),
                       "fit_probs": fit_probs, "decision": a["decision"]["choice"],
                       "decision_probs": a["decision"].get("probs") or a["decision"].get("probabilities"),
                       "truncated": r.get("usage", {}).get("truncated")}
                if fit_probs: row["fit_ev"] = sum(i * float(q) for i, q in enumerate(fit_probs.values() if isinstance(fit_probs, dict) else fit_probs))
                out.write(json.dumps(row, ensure_ascii=False) + "\n"); rows.append(row)
            pos = [r["p_meets"] for r in rows if r["truth"]["meets"]]
            neg = [r["p_meets"] for r in rows if not r["truth"]["meets"]]
            fitv = [r.get("fit_ev", r["fit"]) for r in rows]
            s = {"style": style, "lang": lang,
                 "meets_auc": round(auc(pos, neg), 3),
                 "meets_acc@0.5": sum((r["p_meets"] >= 0.5) == r["truth"]["meets"] for r in rows) / len(rows),
                 "p_meets_true_mean": round(statistics.mean(pos), 3), "p_meets_false_mean": round(statistics.mean(neg), 3),
                 "fit_spearman": round(spearman(fitv, [r["truth"]["fit"] for r in rows]), 3),
                 "decision_acc": sum(r["decision"] == r["truth"]["decision"] for r in rows) / len(rows),
                 "decision_counts": {k: sum(r["decision"] == k for r in rows) for k in ("interview", "maybe", "reject")},
                 "ms_median": statistics.median(r["ms"] for r in rows)}
            summary.append(s); print(json.dumps(s, ensure_ascii=False))
    (HERE.parent / "results" / "laya_summary.json").write_text(json.dumps({"load_s": round(load_s, 1), "runs": summary}, indent=1, ensure_ascii=False), encoding="utf8")


if __name__ == "__main__":
    main()
