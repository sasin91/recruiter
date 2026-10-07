"""Draw a stratified, reproducible sample of Danish job ads from the legacy API's ofir.jsonl."""
import html, json, os, random, re, sys
from html.parser import HTMLParser

SRC = os.environ.get("OFIR_JSONL", "scrapers/ofir/ofir.jsonl")  # the legacy API checkout's scraper output
OUT = "eval/data/ads.jsonl"
N = int(sys.argv[1]) if len(sys.argv) > 1 else 200
DA = {"og", "i", "at", "er", "du", "vi", "til", "med", "en", "et", "har", "af", "for", "på", "som", "det", "den", "dig", "vores", "kan"}
EN = {"the", "and", "you", "we", "to", "of", "with", "our", "for", "is", "are", "will"}

class Text(HTMLParser):
    BLOCK = {"p", "div", "br", "li", "ul", "ol", "h1", "h2", "h3", "h4", "tr", "section"}
    def __init__(self):
        super().__init__(); self.out = []
    def handle_starttag(self, tag, a):
        if tag in self.BLOCK: self.out.append("\n")
        if tag == "li": self.out.append("- ")
    def handle_endtag(self, tag):
        if tag in self.BLOCK: self.out.append("\n")
    def handle_data(self, d): self.out.append(d)

def to_text(body):
    p = Text(); p.feed(body or "")
    t = html.unescape("".join(p.out)).replace("\xa0", " ")
    t = re.sub(r"[ \t]+", " ", t)
    return re.sub(r"\n\s*\n+", "\n\n", "\n".join(l.strip() for l in t.splitlines())).strip()

def lang(t):
    w = re.findall(r"[a-zæøå]+", t.lower())
    da = sum(x in DA for x in w); en = sum(x in EN for x in w)
    return "da" if da > en * 1.5 else "en" if en > da else "mixed"

rows, seen = [], set()
for line in open(SRC, encoding="utf8"):
    d = json.loads(line)
    text = to_text(d["body"])
    key = (d["title"].strip().lower(), d["company"].strip().lower())
    if key in seen or not 600 <= len(text) <= 7000: continue
    seen.add(key)
    if lang(text) != "da": continue
    rows.append({"id": d["ad_id"], "url": d["url"], "title": d["title"], "company": d["company"],
                 "employment_type": d["employment_type"], "address": d["address"], "text": text})

random.seed(42)
# Stratify on text length (short/medium/long) x employment type so Deltid and long ads are represented.
buckets = {}
for r in rows:
    b = (min(len(r["text"]) // 2000, 2), r["employment_type"] != "Fuldtid")
    buckets.setdefault(b, []).append(r)
picked = []
for b, rs in sorted(buckets.items()):
    k = max(5, round(N * len(rs) / len(rows)))
    picked += random.sample(rs, min(k, len(rs)))
random.shuffle(picked)
picked = picked[:N]
with open(OUT, "w", encoding="utf8") as f:
    for i, r in enumerate(picked):
        f.write(json.dumps({"n": i, **r}, ensure_ascii=False) + "\n")
print(f"{len(rows)} unique Danish ads, wrote {len(picked)}", {k: len(v) for k, v in sorted(buckets.items())})
