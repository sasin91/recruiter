// Static server for the eval folder plus POST /result, which appends a JSON line to results/<run>.jsonl.
import { createServer } from "node:http";
import { readFile, appendFile } from "node:fs/promises";
import { extname, join, normalize } from "node:path";

const ROOT = new URL("..", import.meta.url).pathname.replace(/^\/([A-Z]:)/, "$1");
const TYPES = { ".html": "text/html", ".mjs": "text/javascript", ".js": "text/javascript", ".json": "application/json", ".jsonl": "text/plain" };
const PORT = Number(process.env.PORT || 8765);

createServer(async (req, res) => {
  try {
    if (req.method === "POST" && req.url.startsWith("/result")) {
      let body = ""; for await (const c of req) body += c;
      const { run, ...row } = JSON.parse(body);
      await appendFile(join(ROOT, "results", run.replace(/[^\w.-]/g, "_") + ".jsonl"), JSON.stringify(row) + "\n");
      return res.end("ok");
    }
    const path = normalize(join(ROOT, decodeURIComponent(new URL(req.url, "http://x").pathname)));
    if (!path.startsWith(normalize(ROOT))) return res.writeHead(403).end();
    const data = await readFile(path);
    res.writeHead(200, { "content-type": (TYPES[extname(path)] || "application/octet-stream") + "; charset=utf-8" }).end(data);
  } catch (e) { res.writeHead(404).end(String(e)); }
}).listen(PORT, "127.0.0.1", () => console.log(`http://127.0.0.1:${PORT}/bench/`));
