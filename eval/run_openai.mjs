// Run an OpenAI model over the eval ads with strict structured outputs, one call per ad.
// Usage: OPENAI_API_KEY=... node run_openai.mjs <model> [from] [to]
// Writes results/openai__<model>.jsonl in the same shape as the browser bench, so score.mjs picks it up.
import { readFileSync, appendFileSync, existsSync } from "node:fs";
import { SYSTEM_PROMPT, adInput } from "./prompt.mjs";

const [model, from = "0", to = "200"] = process.argv.slice(2);
if (!model || !process.env.OPENAI_API_KEY) { console.error("usage: OPENAI_API_KEY=... node run_openai.mjs <model> [from] [to]"); process.exit(1); }

// Strict mode rejects some keywords; drop them (the scorer still checks ranges and enums).
const strip = (s) => {
  if (Array.isArray(s)) return s.map(strip);
  if (s && typeof s === "object") return Object.fromEntries(Object.entries(s).filter(([k]) => !["$comment", "minimum", "maximum"].includes(k)).map(([k, v]) => [k, strip(v)]));
  return s;
};
const schema = strip(JSON.parse(readFileSync(new URL("./schema.json", import.meta.url), "utf8")));
const out = new URL(`./results/openai__${model}.jsonl`, import.meta.url);
const done = new Set(existsSync(out) ? readFileSync(out, "utf8").split("\n").filter(Boolean).map((l) => JSON.parse(l).n) : []);
const ads = readFileSync(new URL("./data/ads.jsonl", import.meta.url), "utf8").trim().split("\n").map(JSON.parse)
  .filter((a) => a.n >= +from && a.n < +to && !done.has(a.n));

for (const ad of ads) {
  const t0 = performance.now();
  let row;
  try {
    const res = await fetch("https://api.openai.com/v1/chat/completions", {
      method: "POST",
      headers: { "content-type": "application/json", authorization: `Bearer ${process.env.OPENAI_API_KEY}` },
      body: JSON.stringify({
        model,
        messages: [{ role: "system", content: SYSTEM_PROMPT }, { role: "user", content: adInput(ad) }],
        ...(/^gpt-5/.test(model) ? { reasoning_effort: "low" } : {}),
        response_format: { type: "json_schema", json_schema: { name: "job_posting", strict: true, schema } },
      }),
    });
    const body = await res.json();
    if (!res.ok) throw new Error(`${res.status} ${body.error?.message}`);
    const raw = body.choices[0].message.content;
    row = { n: ad.n, ms: Math.round(performance.now() - t0), out: JSON.parse(raw), usage: body.usage };
  } catch (e) {
    row = { n: ad.n, ms: Math.round(performance.now() - t0), out: null, error: String(e) };
  }
  appendFileSync(out, JSON.stringify(row) + "\n");
  console.log(`n=${ad.n} ${row.ms}ms ${row.error ?? row.out.jobtitle}`);
}
