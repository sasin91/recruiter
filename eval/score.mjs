// Score extractor runs against the gold labels, per field.
// Usage: node score.mjs [results/*.jsonl ...]   (defaults to every file in results/ except laya*)
import { readFileSync, readdirSync, writeFileSync } from "node:fs";

const SCALARS = ["joblevel", "workplace_flexibility", "workhours", "education_level", "minimum_experience_years", "workplace_postal_code"];
const SETS = ["skills", "certifications", "education", "responsibilities"];
const FIELDS = ["valid_json", "schema_ok", "jobtitle", ...SCALARS, ...SETS, "skills_required_acc", "certifications_ne", "education_ne"];

const SCHEMA = JSON.parse(readFileSync(new URL("./schema.json", import.meta.url), "utf8"));
const readJsonl = (p) => readFileSync(p, "utf8").split("\n").filter((l) => l.trim()).map((l) => JSON.parse(l));
// MAXN=40 node score.mjs scores every run on the same first 40 ads.
const MAXN = Number(process.env.MAXN || Infinity);
const gold = new Map(readJsonl(new URL("./data/gold.jsonl", import.meta.url)).filter((g) => g.n < MAXN).map((g) => [g.n, g]));

export const norm = (s) => String(s ?? "").toLowerCase().normalize("NFC")
  .replace(/\(.*?\)/g, " ").replace(/[^a-z0-9æøåéü]+/g, " ").trim();

// Character-bigram Dice similarity, multibyte safe (æøå count as letters).
function dice(a, b) {
  a = norm(a); b = norm(b);
  if (a === b) return 1;
  if (a.length < 2 || b.length < 2) return 0;
  const grams = (s) => { const m = new Map(); for (let i = 0; i < s.length - 1; i++) { const g = s.slice(i, i + 2); m.set(g, (m.get(g) || 0) + 1); } return m; };
  const A = grams(a), B = grams(b); let inter = 0;
  for (const [g, c] of A) inter += Math.min(c, B.get(g) || 0);
  return (2 * inter) / (a.length - 1 + b.length - 1);
}
// Same thing, or one contains the other as whole words ("Kørekort" vs "Kørekort kategori B").
const same = (a, b) => { const x = norm(a), y = norm(b); return dice(x, y) >= 0.75 || (x && y && (` ${x} `.includes(` ${y} `) || ` ${y} `.includes(` ${x} `))); };

// Greedy one-to-one matching; returns F1 (1 when both empty) and the matched pairs.
function setF1(pred, gold) {
  pred = (pred || []).filter(Boolean); gold = (gold || []).filter(Boolean);
  if (!pred.length && !gold.length) return { f1: 1, pairs: [] };
  const used = new Set(), pairs = [];
  for (const p of pred) {
    const j = gold.findIndex((g, k) => !used.has(k) && same(p.name ?? p, g.name ?? g));
    if (j >= 0) { used.add(j); pairs.push([p, gold[j]]); }
  }
  const prec = pairs.length / (pred.length || 1), rec = pairs.length / (gold.length || 1);
  return { f1: prec + rec ? (2 * prec * rec) / (prec + rec) : 0, pairs };
}

function schemaOk(o) {
  if (!o || typeof o !== "object") return false;
  for (const k of SCHEMA.required) if (!(k in o)) return false;
  for (const [k, def] of Object.entries(SCHEMA.properties)) if (def.enum && !def.enum.includes(o[k])) return false;
  if (!Array.isArray(o.skills) || o.skills.some((s) => !SCHEMA.properties.skills.items.properties.category.enum.includes(s?.category))) return false;
  return Number.isInteger(o.minimum_experience_years);
}

const sameLevel = (a, b) => a === b || (["Praktikant", "Elev/praktikant"].includes(a) && ["Praktikant", "Elev/praktikant"].includes(b));

export function scoreRow(o, g) {
  const r = { valid_json: o ? 1 : 0, schema_ok: schemaOk(o) ? 1 : 0 };
  o ||= {};
  r.jobtitle = dice(o.jobtitle, g.jobtitle) >= 0.8 || same(o.jobtitle, g.jobtitle) ? 1 : 0;
  for (const k of SCALARS) r[k] = k === "joblevel" ? +sameLevel(o[k], g[k]) : +(String(o[k] ?? "").trim() === String(g[k] ?? "").trim());
  for (const k of SETS) r[k] = setF1(o[k], g[k]).f1;
  // The same F1 only on ads where gold is non-empty, so "nothing found" is not rewarded.
  r.certifications_ne = g.certifications?.length ? r.certifications : null;
  r.education_ne = g.education?.length ? r.education : null;
  const pairs = setF1(o.skills, g.skills).pairs;
  r.skills_required_acc = pairs.length ? pairs.filter(([p, q]) => !!p.is_required === !!q.is_required).length / pairs.length : null;
  return r;
}

const mean = (xs) => { xs = xs.filter((x) => x != null && !Number.isNaN(x)); return xs.length ? xs.reduce((a, b) => a + b, 0) / xs.length : null; };
const pct = (xs, p) => { const s = [...xs].sort((a, b) => a - b); return s.length ? s[Math.min(s.length - 1, Math.floor(p * s.length))] : null; };

export function scoreRun(rows) {
  const byN = new Map(rows.filter((r) => r.n >= 0).map((r) => [r.n, r.out !== undefined ? r : { n: r.n, out: r }]));
  const per = [], ms = [];
  for (const [n, g] of gold) {
    const row = byN.get(n);
    if (!row) continue;
    per.push(scoreRow(row.out, g));
    if (row.ms) ms.push(row.ms);
  }
  const fields = Object.fromEntries(FIELDS.map((f) => [f, mean(per.map((p) => p[f]))]));
  // Overall: mean of the field scores, excluding the JSON/schema checks and the subjective responsibilities.
  const core = ["jobtitle", ...SCALARS, "skills", "certifications", "education"];
  // Hard: the fields a default-filling baseline cannot get right.
  const hard = ["jobtitle", "education_level", "minimum_experience_years", "skills", "certifications_ne", "education_ne"];
  return { n: per.length, overall: mean(core.map((f) => fields[f])), hard: mean(hard.map((f) => fields[f])), fields, ms_median: pct(ms, 0.5), ms_p90: pct(ms, 0.9),
           load_ms: rows.find((r) => r.n === -1)?.loadMs ?? null };
}

if (import.meta.url.endsWith(process.argv[1]?.replace(/\\/g, "/").split("/").pop())) {
  const dir = new URL("./results/", import.meta.url);
  const files = process.argv.slice(2).length ? process.argv.slice(2) : readdirSync(dir).filter((f) => f.endsWith(".jsonl") && !f.startsWith("laya")).map((f) => new URL(f, dir));
  // Runs split over several files (claude__x_a.jsonl, claude__x_b.jsonl) are merged by name.
  const runs = {};
  for (const f of files) { const name = String(f).split("/").pop().replace(/(_[ab]|_part\d+)?\.jsonl$/, ""); (runs[name] ||= []).push(...readJsonl(f)); }
  const out = {};
  for (const [name, rows] of Object.entries(runs)) out[name] = scoreRun(rows);
  const fmt = (x) => (x == null ? "  -  " : (x * 100).toFixed(0).padStart(4) + "%");
  console.log("run".padEnd(42) + "n".padStart(4) + "  overall    hard " + FIELDS.map((f) => f.slice(0, 10).padStart(11)).join("") + "   med ms   p90 ms");
  for (const [name, s] of Object.entries(out).sort((a, b) => b[1].hard - a[1].hard))
    console.log(name.padEnd(42) + String(s.n).padStart(4) + "   " + fmt(s.overall) + "   " + fmt(s.hard) + "  " + FIELDS.map((f) => fmt(s.fields[f]).padStart(11)).join("") + String(s.ms_median ?? "-").padStart(9) + String(s.ms_p90 ?? "-").padStart(9));
  writeFileSync(new URL(`./results/summary${Number.isFinite(MAXN) ? "_n" + MAXN : ""}.json`, import.meta.url), JSON.stringify(out, null, 1));
}
