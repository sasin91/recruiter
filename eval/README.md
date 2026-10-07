# Extractor bake-off and Laya spike

The Danish eval set for the recruiter rebuild (Phase 0 step 3) and the first results. Run on 2026-10-05 on Jonas's machine: Windows 11, AMD RX 7600 (8 GB), 31 GB RAM, Chrome 154.

## Recommendation

1. **Job post → row: use a server LLM call.** gpt-5.4-mini, Claude Haiku 4.5 and gpt-4.1-mini come out roughly level, at 75–80% on the hard fields against 31% for a rules-only baseline. Gemini Nano reaches 47%, and every WebLLM model scores between 17% and 57%.
2. **Drop the in-browser chain.** Nano under-extracts, and Chrome won't accept Danish as a declared language for it. WebLLM needs a 1.5–3 GB download and takes 17–42 s per ad on an RX 7600. It also fills the dropdown fields with near-random values.
3. **Fill postcode and work hours by rule.** The postcode regex matches gold 98% of the time, and ofir's full-time/part-time tag matches gold on 165 of 200 ads.
4. **Replace skills/education/certifications with one `requirements` list.** Each entry has a kind, value, required flag and `alt_group` (agreed with Jonas). Score only hard skills, software and languages; keep soft skills as tags.
5. **Laya: use the English checkpoint and give it code-computed verdicts** (agreed with Jonas). The translated CV plus "meets 3 of 5; missing: …" gets AUC 0.98. The multilingual checkpoint on Danish is close to chance.

## Eval set

- `data/ads.jsonl`: 200 Danish ads from the legacy API's ofir scraper output (13,887 ads, 10,669 unique Danish ones after dedup and filtering). Stratified by length and full/part time, seed 42. Built by `build_sample.py` (point `OFIR_JSONL` at the legacy checkout's `scrapers/ofir/ofir.jsonl`). The ad text belongs to the employers and ofir.dk and names their contact people, so it is not in git: run `build_sample.py` to recreate it. `data/ads.index.jsonl` lists the same 200 ads (n, ad id, URL, title, company) so you can check the rebuild picked the same ones.
- `schema.json`: the legacy `JobPosting` model as JSON Schema. `prompt.mjs` holds the shared Danish prompt and the input format.
- `data/gold.jsonl`: expected fields. Labelled by Claude Opus following `LABELING.md`, one ad at a time, then spot-checked by hand. Known judgement calls: Straffeattest that is only "collected" counts as a certification; when alternatives are listed, the lowest education level is used; the postcode comes from the address line even when that is the recruiter's office (3 of 200 ads).
- `score.mjs`: per-field scoring. Exact match for enums, numbers and postcode. Fuzzy match for the title. Fuzzy set F1 for lists. **hard** is the mean of title, education level, years, skills, and certifications/education on ads where gold is non-empty, i.e. the fields a default-filling baseline can't get. Use `MAXN=40 node score.mjs` to compare all runs on the same ads.

## Extractor results

Ads 0–39 are the only ones every extractor ran on:

| Extractor | Where | Hard fields | All fields | Median time per ad |
|---|---|---|---|---|
| gpt-5.4-mini (reasoning low) | server | **78%** | 87% | 3.5 s |
| Claude Haiku 4.5 | server | 77% | 87% | not measured¹ |
| gpt-4.1-mini | server | 72% | 83% | 1.9 s |
| gpt-5.4-nano (reasoning low) | server | 60% | 80% | 3.1 s |
| WebLLM Qwen3.5 4B | browser | 57% | 68% | 25 s (+6 s cached load) |
| Gemini Nano (Prompt API) | browser | 49% | 76% | 7.3 s (+3 min first download) |
| WebLLM Gemma 2 2B (29 ads) | browser | 33% | 45% | 32 s |
| WebLLM Llama 3.2 3B | browser | 33% | 37% | 18 s |
| Rules only, no model | — | 33% | 67% | 0 |
| WebLLM Qwen3.5 2B | browser | 24% | 33% | 27 s |
| WebLLM Phi-4 mini | browser | 17% | 23% | 42 s |

On ads 0–99, the four server models score gpt-5.4-mini 80%, gpt-4.1-mini 77%, Haiku 75% and gpt-5.4-nano 62% on the hard fields. Nano scores 47%, and 45% across all 200 ads. The GPT models used about 1,650 input tokens per ad, with 230–560 output tokens.

¹ The Haiku runs went through Claude Code subagents, 5 ads each, because this machine has no Anthropic API key, so no API latency was measured. The first Haiku attempt, at 100 ads per agent, skimmed and scored much lower; it is kept in `results/discarded/`.

### Where models fail (`node analyze.mjs`)

- **Skills.** Two thirds of the gold skills are soft skills. Haiku's recall is 89% on languages, 88% on software, 65% on hard skills and only 48% on soft skills.
- **Nano** finds 2.6 skills per ad against 5.5 in gold. It leaves education level empty on 36 ads that state one, and finds only 17% of certifications on ads that have any.
- **WebLLM enums.** Qwen3.5 4B labels 20 of 40 ordinary jobs as "Direktion". Llama 3.2 3B says hybrid on nearly every ad. Phi-4 mini runs out of tokens (1,200 max) on 25 of 40. Qwen3.x outputs an empty `<think></think>` block before the JSON even with thinking off, so `bench.mjs` strips it.
- **Schema limits.** 38 of 200 ads list alternatives ("pædagog eller pædagogisk assistent", "uddannet kok eller 5 års erfaring"). Certifications have no required flag. The job levels "Praktikant" and "Elev/praktikant" overlap.

## Laya spike (`laya/`)

`pairs.json` has 20 hand-written job/candidate pairs over 7 gold ads, with truth labels for meets-all, fit 0–4 and decision. `laya` 0.3.28 ran on CPU.

| What Laya sees | Checkpoint | Meets-all AUC | Fit rank corr. | Choice accuracy | ms per pair |
|---|---|---|---|---|---|
| Danish raw job + CV | multilingual | 0.58–0.62 | 0.41–0.43 | 40% | 180–280 |
| Danish per-requirement lines | multilingual | 0.48–0.53 | 0.42–0.49 | 35–40% | 220 |
| English raw (hand-translated) | english | 0.72 | 0.71 | 45% | 620 |
| English summary from code ("meets 3 of 5; missing: …") | english | **0.98** | **0.94** | 20% | 310 |
| Same summary | multilingual | 0.49 | 0.41 | 25% | 120 |

The multilingual checkpoint fails even on English input, so the English checkpoint is the one to use. The interview/maybe/reject choice head was poor everywhere. Use the noul and score heads only. Twenty pairs is a small sample: calibrate thresholds on real recruiter decisions before trusting the probabilities. The plan said Laya has no browser runtime, but its README now points to `laya-ts` (npm) for Node and the browser.

## Running it

```bash
node bench/server.mjs                      # serves eval/ on 127.0.0.1:8765 and collects POST /result
# then open in Chrome: /bench/index.html?engine=nano  or  ?engine=webllm&model=Qwen3.5-4B-q4f16_1-MLC&from=0&to=40
OPENAI_API_KEY=... node run_openai.mjs gpt-5.4-mini 0 100
node score.mjs                             # or MAXN=40 node score.mjs
python laya/run_laya.py                    # in a venv with `pip install laya`; also run_laya_summary.py, run_laya_translated.py
```

Chrome note: Nano only worked when Chrome was launched without Playwright's default flags. `bench/drive.mjs` starts plain Chrome with its own profile and drives it over CDP (`node bench/drive.mjs "engine=nano&from=0&to=200"`).
