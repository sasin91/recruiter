// The CV match page: reads the CV and job post, has the server match them
// with the taxonomy, asks the language model about what the taxonomy can't
// decide, and shows the legacy ranking score. The matching and scoring are PHP (Cv_matcher.php);
// this file only reads files, calls the endpoints and draws the result.
//
// Without the AI match (not signed in, or signed in without an API key of
// their own) the page runs the free match instead: one quick_match call, no
// model, nothing saved. Signed in, a post can still be fetched from a link.
//
// "Add another job post" compares several posts with the same CV: each is
// matched in turn exactly like a single post (and saved, with the AI match),
// and the jobs are listed by score. A post already matched with this CV is
// skipped and shown from its saved match, and so is a single post.

const PDFJS = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/";
const CV_KEY = "cv_match.cv";
// The free match keeps only the CV text, since it reads it on every match.
const CV_TEXT_KEY = "cv_match.cv_text";
const GROUP_NAMES = {
  requirements: "Requirements",
  skills: "Nice to have",
  title: "Job title",
  responsibilities: "Responsibilities",
};

const $ = (id) => document.getElementById(id);
let base;
let signedIn = false;
let aiReady = false;
let maxJobs = 10;

export function start(baseUrl, state) {
  base = baseUrl;
  ({ signedIn, aiReady, maxJobs } = state);
  $("add-job").addEventListener("click", addJob);
  $("cv-file").addEventListener("change", async (e) => {
    $("cv-text").value = await readFile(e.target.files[0]);
  });
  $("job-file").addEventListener("change", async (e) => {
    $("job-text").value = await readFile(e.target.files[0]);
  });
  $("match").addEventListener("click", () => run(match));
  if (aiReady) showCv(loadCv());
  else startFree();
  if (!signedIn) return;
  $("job-fetch").addEventListener("click", () => run(fetchJob));
  $("job-url").addEventListener("keydown", (e) => {
    if (e.key === "Enter") run(fetchJob);
  });
  // A link pasted into the URL field, or alone into the text box, is fetched
  // straight away.
  for (const id of ["job-url", "job-text"]) {
    $(id).addEventListener("paste", (e) => {
      const pasted = e.clipboardData.getData("text").trim();
      if (!isUrl(pasted) || (id === "job-text" && $("job-text").value.trim())) return;
      e.preventDefault();
      $("job-url").value = pasted;
      run(fetchJob);
    });
  }
  $("cv-read")?.addEventListener("click", () => run(readCv));
  loadHistory().catch(() => {});
}

async function run(task) {
  for (const b of document.querySelectorAll("button")) b.disabled = true;
  try {
    await task();
  } catch (error) {
    progress(error.message, true);
  } finally {
    for (const b of document.querySelectorAll("button")) b.disabled = false;
  }
}

function progress(text, isError = false) {
  $("progress").textContent = text;
  $("progress").classList.toggle("error", isError);
}

// ---------- CV ----------

function loadCv() {
  try {
    return JSON.parse(localStorage.getItem(CV_KEY));
  } catch {
    return null;
  }
}

async function readCv() {
  const text = $("cv-text").value.trim();
  if (!text) throw new Error("Upload or paste a CV first.");
  progress("Reading the CV…");
  const profile = await post("extract_cv", { text });
  const cv = { text, profile, read_at: new Date().toISOString() };
  try {
    localStorage.setItem(CV_KEY, JSON.stringify(cv));
  } catch {
    // Kept for this page view only.
  }
  showCv(cv);
  progress("");
  return cv;
}

function showCv(cv) {
  $("cv-input").open = !cv;
  if (!cv) return;
  const p = cv.profile;
  $("cv-status").textContent = `${p.name || "CV"}: ${p.experience_years} years, ${p.skills.length} skills. Read ${new Date(cv.read_at).toLocaleDateString()}.`;
  $("cv-profile").replaceChildren(...[...p.titles, ...p.skills, ...p.languages, ...p.certifications].map((s) => chip(s)));
  $("cv-text").value = cv.text;
}

// ---------- job post from a link ----------

function isUrl(text) {
  return /^https?:\/\/\S+$/i.test(text);
}

// The server fetches the page (public addresses only) and returns its text.
async function fetchJob() {
  const url = $("job-url").value.trim();
  if (!isUrl(url)) throw new Error("Paste a link starting with http:// or https://.");
  progress("Fetching the job post…");
  const page = await fetchPage(url);
  $("job-url").value = page.url;
  $("job-text").value = page.text;
  progress(`Read ${page.text.length.toLocaleString()} characters from ${new URL(page.url).hostname}. Check the text, then match.`);
}

function fetchPage(url) {
  return post("fetch_job", { url });
}

// ---------- the free match ----------

function startFree() {
  let text = "";
  try {
    text = localStorage.getItem(CV_TEXT_KEY) ?? "";
  } catch {
    // No storage: the CV is pasted again next time.
  }
  $("cv-text").value = text;
  showFreeCv(text, null);
  if (signedIn) return;
  $("job-text").addEventListener("paste", (e) => {
    if (!isUrl(e.clipboardData.getData("text").trim())) return;
    e.preventDefault();
    progress("Fetching a post from a link needs an account. Sign in, or paste the post's text here.", true);
  });
}

function showFreeCv(text, profile) {
  $("cv-input").open = !text || !profile;
  if (!text) return;
  if (!profile) {
    $("cv-status").textContent = "CV added. It's read when you match.";
    return;
  }
  const years = profile.experience_years ? `${profile.experience_years} years of experience, ` : "";
  $("cv-status").textContent = `Found ${years}${profile.skills.length} skills and ${profile.titles.length} job titles we know.`;
  const found = [...profile.titles, ...profile.skills];
  const shown = found.slice(0, 30).map((s) => chip(s));
  if (found.length > 30) shown.push(el("span", { className: "muted" }, [`and ${found.length - 30} more`]));
  $("cv-profile").replaceChildren(...shown);
}

const LINKS_NEED_SIGN_IN = "Fetching a post from a link needs an account. Sign in, or paste the post's text.";

function saveCvText(cvText) {
  try {
    localStorage.setItem(CV_TEXT_KEY, cvText);
  } catch {
    // Kept for this page view only.
  }
}

// One post through quick_match, as {job, groups, verdicts (a Map), result, soft_skills}.
async function freeMatchOne(jobText, cvText) {
  const { job, profile, groups, verdicts, result, soft_skills } = await post("quick_match", { job_text: jobText, cv_text: cvText });
  showFreeCv(cvText, profile);
  if (!Object.values(groups).some((items) => items.length)) {
    throw new Error("The free match found no skills it knows in this post. The AI match reads requirements written as sentences too.");
  }
  return { job: { ...job, job_title: job.job_title || job.heading }, groups, verdicts: new Map(Object.entries(verdicts)), result, soft_skills };
}

async function freeMatch() {
  // Signed in, a link is fetched first, as in the AI match.
  const typed = $("job-text").value.trim();
  if (signedIn && isUrl(typed)) $("job-url").value = typed;
  if (signedIn && (isUrl(typed) || (!typed && $("job-url").value.trim()))) await fetchJob();
  const jobText = $("job-text").value.trim();
  const cvText = $("cv-text").value.trim();
  if (!cvText) throw new Error("Upload or paste a CV first.");
  if (!jobText) throw new Error("Paste or upload a job post first.");
  if (isUrl(jobText)) throw new Error(LINKS_NEED_SIGN_IN);

  saveCvText(cvText);

  progress("Matching…");
  $("result").hidden = true;
  const { job, groups, verdicts, result, soft_skills } = await freeMatchOne(jobText, cvText);
  showResult(job, groups, verdicts, result, soft_skills);
  $("batch-result").hidden = true;
  $("laya-block").hidden = true;
  $("application-panel").hidden = true;
  $("upgrade-panel").hidden = false;
  progress("");
  $("result").scrollIntoView({ behavior: "smooth" });
}

// ---------- matching ----------

async function match() {
  if (jobEntries().length > 1) return matchAll();
  if (!aiReady) return freeMatch();

  // A link typed into the text box, or only into the URL field, is fetched first.
  const typed = $("job-text").value.trim();
  if (isUrl(typed)) $("job-url").value = typed;
  if (isUrl(typed) || (!typed && $("job-url").value.trim())) await fetchJob();

  // Checked before the CV is read, so a missing job post costs no model call.
  const text = $("job-text").value.trim();
  if (!text) throw new Error("Paste a link, the text or upload a job post first.");

  const cv = await currentCv();
  // A post already matched with this CV opens its saved match instead.
  progress("Checking your saved matches…");
  const saved = await alreadyMatched(cv, text);
  if (saved) {
    showSaved(saved);
    progress("You matched this post with this CV before, so this is the saved match. Change the CV or the post to match again.");
    return;
  }
  const { job, scored } = await matchOne(text, $("job-url").value.trim(), cv);
  const { groups, verdicts, result, soft_skills, laya_summary } = scored;
  showResult(job, groups, new Map(Object.entries(verdicts)), result, soft_skills);
  $("batch-result").hidden = true;
  // Laya's English checkpoint: both texts in English plus the verdicts.
  $("laya-block").hidden = false;
  $("laya").value = JSON.stringify({ job: job.text_en, cv: cv.profile.text_en || cv.text, summary: laya_summary }, null, 1);
  showDocuments(scored.saved_id, {}, scored.save_error);
  progress("");
  if (scored.saved_id) await loadHistory();
}

// A CV edited or replaced since it was read is read again, so the match
// never uses the previous candidate's profile.
async function currentCv() {
  const cached = loadCv();
  const cvText = $("cv-text").value.trim();
  return cached && (!cvText || cvText === cached.text) ? cached : readCv();
}

// One job post's text through the AI match: extracted, decided by the
// taxonomy, judged by the model where it can't, then scored and saved.
// `step` prefixes the progress lines ("Job 2 of 5: ").
async function matchOne(text, url, cv, step = "") {
  progress(`${step}Reading the job post…`);
  const job = await post("extract_job", { text });

  progress(`${step}Matching with the taxonomy…`);
  const { verdicts: decided, undecided } = await post("decide", { job, profile: cv.profile });

  if (undecided.length) {
    progress(`${step}Asking the model about ${undecided.length} requirement${undecided.length === 1 ? "" : "s"} the taxonomy couldn't decide…`);
    const { verdicts: judged } = await post("judge", {
      cv_text: cv.profile.text_en || cv.text,
      job_title: job.job_title,
      items: undecided,
    });
    for (const v of judged) decided[v.id] = { ...v, by: "llm", method: "judgement" };
  }

  // Scored and saved in one call, with the texts the application is written from.
  const scored = await post("score", {
    job,
    verdicts: decided,
    job_text: text,
    job_url: url,
    cv_name: cv.profile.name,
    cv_text: cv.text,
  });
  return { job, scored };
}

// ---------- several job posts ----------

// Every job post typed in, first box included, as [{text, url, box}]; a box
// holding only a link has it as the url and no text.
function jobEntries() {
  const entries = [];
  const first = $("job-text").value.trim();
  const firstUrl = $("job-url")?.value.trim() ?? "";
  if (isUrl(first)) entries.push({ text: "", url: first, box: $("job-text") });
  else if (first || firstUrl) entries.push({ text: first, url: firstUrl, box: $("job-text") });
  for (const box of document.querySelectorAll("#more-jobs textarea")) {
    const text = box.value.trim();
    if (text) entries.push(isUrl(text) ? { text: "", url: text, box } : { text, url: "", box });
  }
  return entries;
}

function addJob() {
  const count = document.querySelectorAll("#more-jobs .job-entry").length + 1;
  if (count >= maxJobs) {
    progress(`Compare up to ${maxJobs} job posts at a time.`, true);
    return;
  }
  const file = el("input", { type: "file", accept: ".pdf,.txt,.md,.html,.htm" });
  const box = el("textarea", {
    rows: 6,
    placeholder: signedIn ? "Paste a link or the job post text, or upload it above" : "Paste the job post text, or upload it above",
  });
  const remove = el("button", { type: "button", className: "link-button" }, ["Remove"]);
  const entry = el("div", { className: "job-entry" }, [
    el("div", { className: "job-entry-head" }, [el("strong", {}, ["Another job post"]), remove]),
    file,
    box,
  ]);
  file.addEventListener("change", async (e) => {
    box.value = await readFile(e.target.files[0]);
  });
  remove.addEventListener("click", () => entry.remove());
  $("more-jobs").append(entry);
  progress("");
  box.focus();
}

// Each post is matched in turn against the same CV and the list redrawn as
// results come in, best first. A post that fails shows its error in the list
// and the rest carry on.
async function matchAll() {
  const entries = jobEntries();
  if (entries.length > maxJobs) throw new Error(`Compare up to ${maxJobs} job posts at a time.`);
  const cvText = $("cv-text").value.trim();
  if (!cvText && !(aiReady && loadCv())) throw new Error("Upload or paste a CV first.");
  if (!signedIn && entries.some((e) => !e.text)) throw new Error(LINKS_NEED_SIGN_IN);

  let cv = null;
  if (aiReady) cv = await currentCv();
  else saveCvText(cvText);

  $("result").hidden = true;
  $("upgrade-panel").hidden = true;
  const results = [];
  for (const [n, entry] of entries.entries()) {
    const step = `Job ${n + 1} of ${entries.length}: `;
    const found = { entry, label: entry.text ? firstLine(entry.text) : entry.url };
    results.push(found);
    try {
      let { text, url } = entry;
      let saved = null;
      if (!text) {
        progress(`${step}Fetching the job post…`);
        const page = await fetchPage(url);
        url = page.url;
        text = page.text;
        // Kept in its box, so the text can be checked and the match run again without fetching.
        entry.box.value = text;
        if (entry.box === $("job-text")) $("job-url").value = url;
      }
      if (aiReady && !saved) {
        progress(`${step}Checking your saved matches…`);
        saved = await alreadyMatched(cv, text);
      }
      if (saved) {
        Object.assign(found, fromSaved(saved));
      } else if (aiReady) {
        const { job, scored } = await matchOne(text, url, cv, step);
        Object.assign(found, {
          job,
          url,
          groups: scored.groups,
          verdicts: new Map(Object.entries(scored.verdicts)),
          result: scored.result,
          soft_skills: scored.soft_skills,
          saved_id: scored.saved_id,
          save_error: scored.save_error,
        });
      } else {
        progress(`${step}Matching…`);
        Object.assign(found, await freeMatchOne(text, cvText), { url });
      }
    } catch (error) {
      found.error = error.message;
    }
    showBatch(results, entries.length);
  }
  progress("");
  if (!aiReady) $("upgrade-panel").hidden = false;
  if (aiReady) await loadHistory().catch(() => {});
  $("batch-result").scrollIntoView({ behavior: "smooth" });
}

// The saved match of this CV with the same post text, or null.
async function alreadyMatched(cv, text) {
  const { match } = await post("already_matched", { cv_text: cv.text, job_text: text });
  return match;
}

// A saved match as a row of the list.
function fromSaved(m) {
  return {
    job: { job_title: m.job_title, company: m.company },
    url: m.job_url ?? "",
    result: { index: Number(m.score), tag: m.tag },
    savedAt: m.created_at,
    points: `${m.points}/${m.max_points} points`,
    items: m.items,
    saved_id: m.id,
    texts: { application: m.application_text, resume: m.resume_text },
  };
}

function firstLine(text) {
  const line = text.split("\n").find((l) => l.trim()) ?? "";
  return line.length > 80 ? `${line.slice(0, 77)}…` : line;
}

function showBatch(results, total) {
  $("batch-result").hidden = false;
  const done = results.filter((r) => r.result).length;
  const failed = results.filter((r) => r.error).length;
  const skipped = results.filter((r) => r.savedAt).length;
  $("batch-note").textContent =
    `${done} of ${total} matched` +
    (skipped ? ` (${skipped} matched before, shown from your saved matches)` : "") +
    (failed ? `, ${failed} couldn't be matched` : "") +
    (results.length < total ? ". Still working…" : ".") +
    (aiReady ? " Each match is saved with your other matches." : "");
  const ranked = [...results].sort((a, b) => (b.result?.index ?? -1) - (a.result?.index ?? -1));
  $("batch-list").replaceChildren(...ranked.map(batchRow));
}

function batchRow(r) {
  if (r.error) {
    return el("li", { className: "batch-row failed" }, [
      el("div", { className: "batch-summary" }, [
        el("span", { className: "batch-score muted" }, ["–"]),
        el("div", {}, [el("strong", {}, [r.label || "Job post"]), el("div", { className: "error" }, [r.error])]),
      ]),
    ]);
  }
  const heading = [r.job.job_title, r.job.company].filter(Boolean).join(" · ") || r.label || "Job post";
  const host = r.url ? new URL(r.url).hostname : "";
  const details = el("details", {}, [
    el("summary", { className: "batch-summary" }, [
      el("span", { className: "batch-score" }, [`${Math.round(r.result.index * 100)}%`]),
      el("div", {}, [
        el("strong", {}, [heading]),
        el("div", { className: "muted" }, [
          el("span", { className: `tag ${r.result.tag}` }, [r.result.tag]),
          host ? ` · ${host}` : "",
          r.savedAt ? ` · matched before, ${new Date(r.savedAt * 1000).toLocaleDateString()}` : "",
        ]),
      ]),
    ]),
    ...(r.savedAt
      ? [el("div", { className: "muted" }, [`${r.points} · not matched again`]), ...savedSections(r.items)]
      : [el("div", { className: "muted" }, [scoreParts(r.result)]), ...groupSections(r.groups, r.verdicts, r.result), ...softSkills(r.soft_skills)]),
    ...(aiReady ? [el("div", { className: "batch-documents" }, documents(r.saved_id, r.texts ?? {}, r.save_error))] : []),
  ]);
  return el("li", { className: "batch-row" }, [details]);
}

// ---------- result ----------

function showResult(job, groups, verdicts, result, soft) {
  $("result").hidden = false;
  $("score-value").textContent = Math.round(result.index * 100);
  $("score-tag").textContent = result.tag;
  $("score-tag").className = `tag ${result.tag}`;
  $("job-heading").textContent = [job.job_title, job.company].filter(Boolean).join(" · ");
  $("score-parts").textContent = scoreParts(result);
  $("groups").replaceChildren(...groupSections(groups, verdicts, result));
  $("soft-skills").replaceChildren(...softSkills(soft));
}

function scoreParts(result) {
  return Object.entries(result.parts)
    .map(([key, p]) => `${GROUP_NAMES[key]} ${p.percentage}% (${p.value}/${p.weight})`)
    .join(" · ");
}

// The criteria with their verdicts, missing first.
function groupSections(groups, verdicts, result) {
  const sections = [];
  for (const [key, items] of Object.entries(groups)) {
    if (!items.length) continue;
    const order = { missing: 0, partial: 1, met: 2 };
    const sorted = [...items].sort((a, b) => order[verdictOf(verdicts, a)] - order[verdictOf(verdicts, b)]);
    const part = result.parts[key];
    sections.push(
      el("section", { className: "group" }, [
        el("h3", {}, [`${GROUP_NAMES[key]} `, el("span", { className: "muted" }, [`${part.percentage}% · weight ${part.weight}`])]),
        el("ul", {}, sorted.map((item) => row(item, verdicts.get(item.id), item.alternatives?.map((a) => [a, verdicts.get(a.id)])))),
      ]),
    );
  }
  return sections;
}

function softSkills(soft) {
  return soft.length ? [el("h3", {}, ["Soft skills ", el("span", { className: "muted" }, ["not scored"])]), ...soft.map(chip)] : [];
}

// ---------- saved matches ----------

async function loadHistory() {
  const { matches } = await request("GET", "history");
  $("history-empty").hidden = matches.length > 0;
  $("history").replaceChildren(
    ...matches.map((m) => {
      const when = new Date(m.created_at * 1000).toLocaleDateString();
      const written = [Number(m.has_application) && "application", Number(m.has_resume) && "résumé"].filter(Boolean);
      const meta = [`${Math.round(m.score * 100)}% ${m.tag}`, when, ...written].join(" · ");
      const button = el("button", { type: "button" }, [
        el("span", { className: "history-title" }, [[m.job_title, m.company].filter(Boolean).join(" · ") || "Untitled"]),
        el("span", { className: "history-meta" }, [meta]),
      ]);
      button.addEventListener("click", () => run(() => openSaved(m.id)));
      return el("li", {}, [button]);
    }),
  );
}

// A saved match shown like a fresh one, from its stored verdicts.
async function openSaved(id) {
  progress("Opening the saved match…");
  showSaved(await request("GET", `saved/${id}`));
  progress("");
}

function showSaved(m) {
  $("result").hidden = false;
  $("batch-result").hidden = true;
  $("score-value").textContent = Math.round(m.score * 100);
  $("score-tag").textContent = m.tag;
  $("score-tag").className = `tag ${m.tag}`;
  $("job-heading").textContent = [m.job_title, m.company].filter(Boolean).join(" · ");
  $("score-parts").textContent = `${m.points}/${m.max_points} points · saved ${new Date(m.created_at * 1000).toLocaleString()}`;

  $("groups").replaceChildren(...savedSections(m.items));
  $("soft-skills").replaceChildren();
  $("laya-block").hidden = true;
  $("job-url").value = m.job_url ?? "";
  $("job-text").value = m.job_text;
  showDocuments(m.id, { application: m.application_text, resume: m.resume_text });
  $("result").scrollIntoView({ behavior: "smooth" });
}

// A saved match's items by criterion, missing first.
function savedSections(savedItems) {
  const byCriterion = new Map();
  for (const item of savedItems) {
    if (!byCriterion.has(item.criterion)) byCriterion.set(item.criterion, []);
    byCriterion.get(item.criterion).push(item);
  }
  const order = { missing: 0, partial: 1, met: 2 };
  return [...byCriterion].map(([criterion, items]) =>
    el("section", { className: "group" }, [
      el("h3", {}, [GROUP_NAMES[criterion] ?? criterion]),
      el("ul", {}, [...items].sort((a, b) => order[a.verdict] - order[b.verdict]).map((item) => row(item, savedVerdict(item)))),
    ]),
  );
}

function savedVerdict(item) {
  const by = item.decided_by === "llm" ? "llm" : item.decided_by === "years" ? "rule" : "taxonomy";
  return { verdict: item.verdict, by, method: item.decided_by, reason: item.reason, evidence: item.evidence };
}

// ---------- job application and tailored résumé ----------

// What can be written for a saved match, from the CV and the post.
const DOCUMENTS = {
  application: { title: "Job application", write: "Write application", endpoint: "write_application", busy: "Writing the application…" },
  resume: { title: "Tailored résumé", write: "Tailor résumé", endpoint: "tailor_resume", busy: "Tailoring the résumé…" },
};

function showDocuments(id, texts, saveError = "") {
  $("application-panel").hidden = false;
  $("documents").replaceChildren(...documents(id, texts, saveError));
}

// The application and résumé boxes for a saved match: texts already written
// are shown, editable, with copy to clipboard.
function documents(id, texts, saveError = "") {
  if (!id) return [el("p", { className: "error" }, [saveError || "This match wasn't saved, so nothing can be written for it."])];
  return [
    el("p", { className: "muted" }, ["Saved. Write a job application or a résumé tailored to this post, from your CV:"]),
    ...Object.keys(DOCUMENTS).map((kind) => documentBox(kind, id, texts[kind] ?? "")),
  ];
}

function documentBox(kind, id, written) {
  const doc = DOCUMENTS[kind];
  const write = el("button", { type: "button" }, [written ? "Write it again" : doc.write]);
  const text = el("textarea", { rows: 18, hidden: !written, value: written });
  const copy = el("button", { type: "button", hidden: !written }, ["Copy to clipboard"]);
  const status = el("span", { className: "muted", ariaLive: "polite" });
  write.addEventListener("click", () =>
    run(async () => {
      progress(doc.busy);
      const answer = await post(doc.endpoint, { id });
      text.value = answer[kind];
      text.hidden = copy.hidden = false;
      write.textContent = "Write it again";
      status.textContent = "";
      progress("");
      await loadHistory();
    }),
  );
  copy.addEventListener("click", () => copyText(text, status));
  return el("div", { className: "document" }, [
    el("h4", {}, [doc.title]),
    write,
    text,
    el("div", { className: "actions" }, [copy, status]),
  ]);
}

// The textarea's current text, so edits made on the page are what gets copied.
async function copyText(area, status) {
  try {
    await navigator.clipboard.writeText(area.value);
  } catch {
    // Older browsers or a page without clipboard permission: copy the selection.
    area.select();
    if (!document.execCommand("copy")) {
      status.textContent = "Couldn't copy: select the text and copy it yourself.";
      return;
    }
  }
  status.textContent = "Copied.";
}

function verdictOf(verdicts, item) {
  return verdicts.get(item.id)?.verdict ?? "missing";
}

function row(item, v = { verdict: "missing", by: "llm", reason: "No verdict returned." }, alternatives = null) {
  const by = v.by === "llm" ? "model" : v.by === "rule" ? "rule" : v.method === "not_found" ? "keyword match" : `taxonomy, ${v.method}`;
  return el("li", { className: `item ${v.verdict}` }, [
    el("span", { className: `badge ${v.verdict}` }, [v.verdict]),
    el("div", {}, [
      el("strong", {}, [item.text]),
      el("div", { className: "why" }, [v.reason, v.evidence ? el("q", {}, [v.evidence]) : ""]),
      el("div", { className: "by muted" }, [by]),
      alternatives ? el("ul", { className: "alternatives" }, alternatives.map(([a, av]) => row(a, av))) : "",
    ]),
  ]);
}

function chip(text) {
  return el("span", { className: "chip" }, [text]);
}

function el(tag, props, children = []) {
  const node = Object.assign(document.createElement(tag), props);
  node.append(...children);
  return node;
}

// ---------- io ----------

function post(method, body) {
  return request("POST", method, body);
}

async function request(verb, method, body) {
  const response = await fetch(new URL(`cv_match/${method}`, base), {
    method: verb,
    headers: body === undefined ? {} : { "Content-Type": "application/json" },
    body: body === undefined ? undefined : JSON.stringify(body),
  });
  const data = await response.json().catch(() => ({ error: `${method} failed: ${response.status}` }));
  if (!response.ok || data.error) throw new Error(data.error ?? `${method} failed: ${response.status}`);
  return data;
}

async function readFile(file) {
  if (!file) return "";
  if (file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf")) return readPdf(file);
  const text = await file.text();
  if (/\.html?$/i.test(file.name)) return new DOMParser().parseFromString(text, "text/html").body.innerText;
  return text;
}

async function readPdf(file) {
  const pdfjs = await import(`${PDFJS}pdf.min.mjs`);
  pdfjs.GlobalWorkerOptions.workerSrc = `${PDFJS}pdf.worker.min.mjs`;
  const pdf = await pdfjs.getDocument({ data: await file.arrayBuffer() }).promise;
  const pages = [];
  for (let n = 1; n <= pdf.numPages; n++) {
    const content = await (await pdf.getPage(n)).getTextContent();
    let line = "";
    const lines = [];
    for (const item of content.items) {
      line += item.str;
      if (item.hasEOL) {
        lines.push(line);
        line = "";
      }
    }
    lines.push(line);
    pages.push(lines.join("\n"));
  }
  return pages.join("\n\n").replace(/\n{3,}/g, "\n\n").trim();
}
