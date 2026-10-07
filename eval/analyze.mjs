// Error analysis for job-post parsing: skills precision/recall, education level confusions, field-level misses.
import { readFileSync, readdirSync } from "node:fs";
import { norm } from "./score.mjs";
const rj = (p) => readFileSync(p, "utf8").split("\n").filter(Boolean).map(JSON.parse);
const gold = new Map(rj("data/gold.jsonl").map((g) => [g.n, g]));
const runs = {};
for (const f of readdirSync("results").filter((f) => f.endsWith(".jsonl") && !f.startsWith("laya"))) {
  const name = f.replace(/(_part\d+)?\.jsonl$/, "");
  for (const r of rj("results/" + f)) if (r.n >= 0) (runs[name] ||= new Map()).set(r.n, r.out !== undefined ? r.out : r);
}
const same = (a, b) => { const x = norm(a), y = norm(b); return x === y || ` ${x} `.includes(` ${y} `) || ` ${y} `.includes(` ${x} `); };
for (const name of ["claude__haiku-4.5", "nano__gemini-nano", "webllm__Qwen3.5-4B-q4f16_1-MLC"]) {
  const m = runs[name]; let tp = 0, np = 0, ng = 0, reqTP = 0, reqN = 0; const conf = {}; const cat = {};
  let soft = [0, 0];
  for (const [n, o] of m) {
    const g = gold.get(n); if (!o || !g || n >= 100) continue;
    const ps = (o.skills || []).map((s) => s.name), gs = g.skills.map((s) => s.name);
    np += ps.length; ng += gs.length;
    for (const gsk of g.skills) {
      const hit = (o.skills || []).find((p) => same(p.name, gsk.name));
      cat[gsk.category] ||= [0, 0]; cat[gsk.category][1]++; if (hit) { cat[gsk.category][0]++; tp++; }
    }
    const k = `${g.education_level || "(none)"} -> ${o.education_level || "(none)"}`; if (g.education_level !== o.education_level) conf[k] = (conf[k] || 0) + 1;
  }
  console.log(`\n${name}: skills/ad pred ${(np / m.size).toFixed(1)} gold ${(ng / m.size).toFixed(1)}, recall ${(100 * tp / ng).toFixed(0)}% precision ~${(100 * tp / np).toFixed(0)}%`);
  console.log("  recall by gold category:", Object.entries(cat).map(([c, [h, t]]) => `${c} ${Math.round(100 * h / t)}% (${t})`).join(", "));
  console.log("  top education_level misses:", Object.entries(conf).sort((a, b) => b[1] - a[1]).slice(0, 4).map(([k, v]) => `${k} x${v}`).join("; "));
}
// How often the gold postcode is not the address-line postcode (recruiter/HQ address problem).
const ads = new Map(rj("data/ads.jsonl").map((a) => [a.n, a]));
let diff = 0, none = 0;
for (const [n, g] of gold) { const pc = (ads.get(n).address.match(/\b(\d{4})\b/) || [])[1] || ""; if (!pc) none++; else if (pc !== g.workplace_postal_code) diff++; }
console.log(`\npostcode: address line has none in ${none}/200, gold differs from address line in ${diff}/200`);
let hrs = 0; for (const [n, g] of gold) { const meta = ads.get(n).employment_type.startsWith("Deltid") ? "Part time" : "Full time"; if (meta === g.workhours) hrs++; }
console.log(`workhours from ofir employment_type metadata alone: ${hrs}/200 match gold`);
const withEdu = [...gold.values()].filter((g) => g.education.length > 1).length;
console.log(`ads listing 2+ alternative educations: ${withEdu}/200; with certifications: ${[...gold.values()].filter((g) => g.certifications.length).length}/200; Straffeattest/Børneattest among certs: ${[...gold.values()].filter((g) => g.certifications.some((c) => /attest/i.test(c))).length}`);
