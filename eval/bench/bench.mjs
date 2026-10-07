import { SYSTEM_PROMPT, adInput } from "../prompt.mjs";

const q = new URLSearchParams(location.search);
const engine = q.get("engine") || "nano";
const model = q.get("model") || "gemini-nano";
const from = Number(q.get("from") || 0), to = Number(q.get("to") || 200);
const run = `${engine}__${model}`;
const logEl = document.getElementById("log");
const log = (m) => { logEl.textContent += m + "\n"; console.log(m); };
window.benchState = { done: 0, failed: 0, finished: false, run };

const schema = await (await fetch("../schema.json")).json();
delete schema.$comment;
const ads = (await (await fetch("../data/ads.jsonl")).text()).trim().split("\n").map(JSON.parse).filter((a) => a.n >= from && a.n < to);

async function post(row) {
  await fetch("/result", { method: "POST", body: JSON.stringify({ run, ...row }) });
}

async function makeNano() {
  const t0 = performance.now();
  // Nano attests only de/en/es/fr/ja; Danish is not accepted as a declared language, so declare en and send Danish anyway.
  const langs = { expectedInputs: [{ type: "text", languages: ["en"] }], expectedOutputs: [{ type: "text", languages: ["en"] }] };
  const avail = await LanguageModel.availability(langs);
  log(`nano availability: ${avail}`);
  const base = await LanguageModel.create({
    ...langs,
    initialPrompts: [{ role: "system", content: SYSTEM_PROMPT }],
    monitor(m) { m.addEventListener("downloadprogress", (e) => log(`download ${Math.round(e.loaded * 100)}%`)); },
  });
  log(`nano ready in ${Math.round(performance.now() - t0)} ms, contextWindow=${base.contextWindow ?? base.inputQuota}`);
  return {
    loadMs: performance.now() - t0,
    async extract(text) {
      const s = await base.clone();
      try { return await s.prompt(text, { responseConstraint: schema }); } finally { s.destroy(); }
    },
  };
}

async function makeWebLLM() {
  const webllm = await import("https://cdn.jsdelivr.net/npm/@mlc-ai/web-llm@0.2.85/+esm");
  const t0 = performance.now();
  let last = 0;
  // The Cache API backend fails with "Entry already exists" after an interrupted download; IndexedDB does not.
  const appConfig = { ...webllm.prebuiltAppConfig, cacheBackend: "indexeddb" };
  const eng = await webllm.CreateMLCEngine(model, {
    appConfig,
    initProgressCallback: (p) => { if (performance.now() - last > 3000) { last = performance.now(); log(p.text); } },
  }, { context_window_size: 8192 });
  log(`webllm ${model} ready in ${Math.round(performance.now() - t0)} ms`);
  const isQwen3 = /^Qwen3/.test(model);
  return {
    loadMs: performance.now() - t0,
    async extract(text) {
      const r = await eng.chat.completions.create({
        messages: [{ role: "system", content: SYSTEM_PROMPT }, { role: "user", content: text }],
        response_format: { type: "json_object", schema: JSON.stringify(schema) },
        temperature: 0, max_tokens: 1200,
        ...(isQwen3 ? { extra_body: { enable_thinking: false } } : {}),
      });
      this.usage = r.usage;
      // Qwen3.x emits an empty <think></think> block before the constrained JSON even with thinking off.
      return r.choices[0].message.content.replace(/^\s*<think>[\s\S]*?<\/think>\s*/, "");
    },
  };
}

if (q.get("noop")) { throw new Error("noop"); }
let ex;
try { ex = engine === "nano" ? await makeNano() : await makeWebLLM(); }
catch (e) { log("INIT FAILED " + e); await post({ n: -1, error: String(e) }); window.benchState.finished = true; throw e; }
await post({ n: -1, loadMs: Math.round(ex.loadMs), ua: navigator.userAgent });
for (const ad of ads) {
  const t0 = performance.now();
  let raw = null, error = null;
  try { raw = await ex.extract(adInput(ad)); } catch (e) { error = String(e); }
  const ms = Math.round(performance.now() - t0);
  let out = null;
  try { out = JSON.parse(raw); } catch (e) { error ??= "json: " + e.message; }
  await post({ n: ad.n, ms, out, raw: out ? undefined : raw, error, usage: ex.usage });
  window.benchState.done++; if (error) window.benchState.failed++;
  log(`n=${ad.n} ${ms}ms ${error ? "ERR " + error.slice(0, 120) : out.jobtitle}`);
}
window.benchState.finished = true;
log("FINISHED");
