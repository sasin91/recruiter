// Launch plain Chrome (no Playwright default flags) and drive it over CDP.
// Needs `npm i playwright-core` (not a dependency of the app). Nano downloads into the profile on first use (~4 GB).
// Usage: node bench/drive.mjs probe | node bench/drive.mjs "<bench query>"   (needs bench/server.mjs running)
import { spawn } from "node:child_process";
import { chromium } from "playwright-core";
const chrome = spawn("C:/Program Files/Google/Chrome/Application/chrome.exe", [
  `--user-data-dir=${process.env.PROFILE || process.cwd() + "/.bench-profile"}`, "--remote-debugging-port=9333", "--no-first-run", "--no-default-browser-check",
  "--enable-features=OptimizationGuideOnDeviceModel:BypassPerfRequirement/true,PromptAPIForGeminiNano", "about:blank"], { stdio: "ignore" });
let browser;
for (let i = 0; i < 30 && !browser; i++) { await new Promise(r => setTimeout(r, 1000)); browser = await chromium.connectOverCDP("http://127.0.0.1:9333").catch(() => null); }
const page = browser.contexts()[0].pages()[0];
page.on("console", (m) => { const t = m.text(); if (!t.startsWith("Failed to load")) console.log("[page]", t.slice(0, 300)); });
const arg = process.argv[2];
if (arg === "probe") {
  await page.goto("http://127.0.0.1:8765/bench/index.html?noop=1");
  await page.waitForTimeout(Number(process.argv[3] || 15000));
  console.log(await page.evaluate(async () => {
    const o = { expectedInputs: [{ type: "text", languages: ["en"] }], expectedOutputs: [{ type: "text", languages: ["en"] }] };
    const avail = await LanguageModel.availability(o);
    try { const t = performance.now(); const s = await LanguageModel.create({ ...o, monitor(m) { m.addEventListener("downloadprogress", e => console.log("dl " + Math.round(e.loaded * 100))); } });
      return avail + " | " + (await s.prompt("Hvad er hovedstaden i Danmark? Svar kort.")) + ` (${Math.round(performance.now() - t)} ms) quota=${s.inputQuota}`;
    } catch (e) { return avail + " | " + e; }
  }));
} else {
  await page.goto("http://127.0.0.1:8765/bench/index.html?" + arg);
  await page.waitForFunction(() => window.benchState?.finished, null, { timeout: 0, polling: 5000 });
}
await browser.close().catch(() => {}); chrome.kill();
