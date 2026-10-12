// The /tldr-slop page: sends the link (or pasted text) to tldr-slop/check
// and draws the answer. The reading and the verdict happen on the server
// (Tldr_slop.php); this file only shows them.

const $ = (id) => document.getElementById(id);

const percent = (p) => `${Math.round(p * 100)}%`;

function showError(text) {
  $("error").textContent = text;
  $("error").hidden = !text;
}

function el(tag, text, className = "") {
  const node = document.createElement(tag);
  node.textContent = text;
  if (className) node.className = className;
  return node;
}

function drawVerdict(answer) {
  const box = $("verdict");
  box.replaceChildren();
  if (answer.laya) {
    const slop = answer.laya.verdict === "slop";
    box.className = `verdict ${slop ? "is-slop" : "not-slop"}`;
    box.append(
      el("strong", slop ? "Slop" : "Not slop"),
      el("span", ` Laya: ${percent(answer.laya.slop)} likely slop, substance ${answer.laya.substance.toFixed(1)} of 4 (${answer.laya.substance_label}).`),
    );
  } else {
    box.className = "verdict unknown";
    box.append(el("strong", "No verdict"), el("span", ` ${answer.laya_error}`));
  }
}

function draw(answer) {
  drawVerdict(answer);
  const title = $("title");
  title.replaceChildren();
  if (answer.url) {
    const link = el("a", answer.title || answer.url);
    link.href = answer.url;
    link.rel = "noopener noreferrer";
    title.append(link);
  } else {
    title.textContent = answer.title || "Pasted text";
  }
  $("tldr").replaceChildren(...answer.tldr.map((s) => el("li", s)));
  $("summary").textContent = answer.summary;
  $("legend").textContent = "(filler is marked, repeats are struck through)";
  $("sentences").replaceChildren(
    ...answer.sentences.map((s) => {
      const classes = [s.filler ? "filler" : "", s.repeats !== null ? "repeat" : ""].join(" ").trim();
      const item = el("li", s.text, classes);
      item.title = `Leans ${s.lean > 0 ? "to filler" : "to substance"} by ${Math.abs(s.lean).toFixed(2)}`
        + (s.repeats !== null ? `; repeats sentence ${s.repeats + 1}` : "");
      return item;
    }),
  );
  $("result").hidden = false;
}

async function check(event) {
  event.preventDefault();
  const url = $("article-url").value.trim();
  const text = $("article-text").value.trim();
  showError("");
  if (!url && !text) {
    showError("Paste a link to an article, or its text.");
    return;
  }
  const button = $("check");
  button.disabled = true;
  button.querySelector(".label").textContent = "Reading…";
  try {
    const response = await fetch("tldr-slop/check", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(url ? { url } : { text }),
    });
    let answer;
    try {
      answer = await response.json();
    } catch {
      throw new Error(`The server answered HTTP ${response.status} without a result. Try again.`);
    }
    if (answer.error) throw new Error(answer.error);
    draw(answer);
  } catch (error) {
    showError(error.message === "Failed to fetch" ? "Couldn't reach the server. Check your connection and try again." : error.message);
  } finally {
    button.disabled = false;
    button.querySelector(".label").textContent = "Check";
  }
}

$("check-form").addEventListener("submit", check);
