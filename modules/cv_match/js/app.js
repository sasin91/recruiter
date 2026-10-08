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
//
// ui.js holds the dialogs, toasts and animations; this file decides when
// they're shown.

import { confirmDialog, countUp, dropZone, flash, flip, reveal, toast } from "./ui.js";

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
// Job posts as read for each saved match this page made, by saved id, so a
// tailored résumé is scored on the same requirements as the CV was.
const readJobs = new Map();

export function start(baseUrl, state) {
  base = baseUrl;
  ({ signedIn, aiReady, maxJobs } = state);
  $("add-job").addEventListener("click", addJob);
  fileInput($("cv-file"), $("cv-drop"), $("cv-text"), $("cv-file-name"));
  fileInput($("job-file"), $("job-drop"), $("job-text"), $("job-file-name"));
  for (const id of ["cv-text", "job-text"]) $(id).addEventListener("input", markSteps);
  $("match").addEventListener("click", (e) => run(match, e.currentTarget));
  showJobCount();
  if (aiReady) showCv(loadCv());
  else startFree();
  markSteps();
  if (!signedIn) return;
  $("job-fetch").addEventListener("click", (e) => run(fetchJob, e.currentTarget));
  $("job-url").addEventListener("keydown", (e) => {
    if (e.key === "Enter") run(fetchJob, $("job-fetch"));
  });
  // A link pasted into the URL field, or alone into the text box, is fetched
  // straight away.
  for (const id of ["job-url", "job-text"]) {
    $(id).addEventListener("paste", (e) => {
      const pasted = e.clipboardData.getData("text").trim();
      if (!isUrl(pasted) || (id === "job-text" && $("job-text").value.trim())) return;
      e.preventDefault();
      $("job-url").value = pasted;
      run(fetchJob, $("job-fetch"));
    });
  }
  $("cv-read")?.addEventListener("click", (e) => run(readCv, e.currentTarget));
  loadHistory();
}

// One task at a time: every button is disabled while it runs, and the button
// that started it shows a spinner.
async function run(task, button = null) {
  for (const b of document.querySelectorAll("main button")) b.disabled = true;
  button?.classList.add("busy");
  button?.setAttribute("aria-busy", "true");
  document.body.classList.add("working");
  try {
    await task();
  } catch (error) {
    progress(error.message, true);
  } finally {
    for (const b of document.querySelectorAll("main button")) b.disabled = false;
    button?.classList.remove("busy");
    button?.removeAttribute("aria-busy");
    document.body.classList.remove("working");
    progressBar(null);
  }
}

function progress(text, isError = false) {
  const line = $("progress");
  line.textContent = text;
  line.classList.toggle("error", isError);
  // Shaken, so an error after a long wait is noticed.
  line.classList.remove("shake");
  if (isError) {
    void line.offsetWidth;
    line.classList.add("shake");
  }
}

// The bar under the buttons: `done` of `total` steps, indeterminate while a
// task runs without a count, hidden with null.
function progressBar(done, total = 0) {
  const bar = $("progress-bar");
  bar.hidden = done === null;
  if (done === null) return;
  bar.classList.toggle("indeterminate", !total);
  bar.style.setProperty("--done", total ? done / total : 0);
  if (total) bar.setAttribute("aria-valuenow", Math.round((done / total) * 100));
  else bar.removeAttribute("aria-valuenow");
}

// The numbered steps by the panel headings turn into ticks once filled in.
function markSteps() {
  $("cv-step").classList.toggle("done", $("cv-text").value.trim() !== "");
  $("job-step").classList.toggle("done", jobEntries().length > 0);
}

// A file picked or dropped is read into its text box.
function fileInput(input, zone, box, nameLabel) {
  const load = async (file) => {
    if (!file) return;
    nameLabel.textContent = file.name;
    zone.classList.add("loading");
    try {
      box.value = await readFile(file);
      box.dispatchEvent(new Event("input"));
      reveal([box]);
    } catch (error) {
      progress(`Couldn't read ${file.name}: ${error.message}`, true);
    } finally {
      zone.classList.remove("loading");
    }
  };
  input.addEventListener("change", (e) => load(e.target.files[0]));
  dropZone(zone, load);
  dropZone(box, load);
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
  toast(`CV read: ${profile.skills.length} skills.`, "success");
  return cv;
}

function showCv(cv) {
  $("cv-input").open = !cv;
  if (!cv) return;
  const p = cv.profile;
  $("cv-status").textContent = `${p.name || "CV"}: ${p.experience_years} years, ${p.skills.length} skills. Read ${new Date(cv.read_at).toLocaleDateString()}.`;
  $("cv-profile").replaceChildren(...[...p.titles, ...p.skills, ...p.languages, ...p.certifications].map((s) => chip(s)));
  reveal($("cv-profile").children);
  $("cv-text").value = cv.text;
  markSteps();
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
  reveal([$("job-text")]);
  markSteps();
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
  markSteps();
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
  reveal($("cv-profile").children);
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
  const { job, profile, groups, verdicts, result, soft_skills, laya, laya_error } = await post("quick_match", { job_text: jobText, cv_text: cvText });
  showFreeCv(cvText, profile);
  if (!Object.values(groups).some((items) => items.length)) {
    throw new Error("The free match found no skills it knows in this post. The AI match reads requirements written as sentences too.");
  }
  return { job: { ...job, job_title: job.job_title || job.heading }, groups, verdicts: new Map(Object.entries(verdicts)), result, soft_skills, laya, laya_error };
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
  progressBar(0);
  $("result").hidden = true;
  const { job, groups, verdicts, result, soft_skills, laya, laya_error } = await freeMatchOne(jobText, cvText);
  showResult(job, groups, verdicts, result, soft_skills);
  $("batch-result").hidden = true;
  $("laya-block").hidden = true;
  showLaya(laya, laya_error);
  $("application-panel").hidden = true;
  $("upgrade-panel").hidden = false;
  progress("");
  $("result").scrollIntoView({ behavior: "smooth" });
}

// ---------- matching ----------

async function match() {
  if (jobEntries().length > 1) return matchAll();
  progressBar(0);
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
  showLaya(scored.laya, scored.laya_error);
  showDocuments(scored.saved_id, {}, scored.save_error);
  progress("");
  if (scored.save_error) toast(scored.save_error, "error");
  if (scored.saved_id) await loadHistory();
}

// Laya's answer on the summary: how likely the CV meets every requirement and
// the expected fit (0-4). Nothing when the server has no Laya service.
function showLaya(laya, error) {
  const answer = $("laya-answer");
  answer.className = laya ? "" : "muted";
  answer.textContent = laya
    ? `Laya: ${Math.round(laya.meets * 100)}% likely to meet every requirement, fit ${laya.fit.toFixed(1)} of 4 (${laya.fit_label}).`
    : error ? `No Laya reading this time. ${error} The score above doesn't depend on it.` : "";
  answer.hidden = !laya && !error;
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
async function matchOne(text, url, cv, step = "", tick = () => {}, readJob = null) {
  progress(`${step}Reading the job post…`);
  const job = readJob ?? (await post("extract_job", { text }));

  tick();
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
  tick();

  // Scored and saved in one call, with the texts the application is written from.
  const scored = await post("score", {
    job,
    verdicts: decided,
    job_text: text,
    job_url: url,
    cv_name: cv.profile.name,
    cv_text: cv.text,
  });
  if (scored.saved_id) readJobs.set(scored.saved_id, job);
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
  const fileName = el("span", { className: "drop-name" });
  const zone = el("label", { className: "drop compact" }, [
    file,
    el("span", { className: "drop-text" }, [el("strong", {}, ["Choose a file"]), " or drop it here"]),
    fileName,
  ]);
  const box = el("textarea", {
    rows: 6,
    placeholder: signedIn ? "Paste a link or the job post text, or upload it above" : "Paste the job post text, or upload it above",
  });
  const remove = el("button", { type: "button", className: "link-button" }, ["Remove"]);
  const entry = el("div", { className: "job-entry entering" }, [
    el("div", { className: "job-entry-head" }, [el("strong", { className: "job-entry-title" }), remove]),
    zone,
    box,
  ]);
  fileInput(file, zone, box, fileName);
  box.addEventListener("input", markSteps);
  remove.addEventListener("click", async () => {
    // Text typed or fetched into the box is lost with it, so that's asked first.
    if (
      box.value.trim() &&
      !(await confirmDialog({ title: "Remove this job post?", text: "Its text goes with it.", confirm: "Remove", tone: "danger" }))
    ) {
      return;
    }
    entry.classList.add("leaving");
    const done = () => {
      entry.remove();
      showJobCount();
      markSteps();
    };
    if (matchMedia("(prefers-reduced-motion: reduce)").matches) done();
    else entry.addEventListener("animationend", done, { once: true });
  });
  $("more-jobs").append(entry);
  showJobCount();
  progress("");
  box.focus();
}

// "Job post 2", "Job post 3"… on the extra boxes, and how many are left on the button.
function showJobCount() {
  const entries = document.querySelectorAll("#more-jobs .job-entry:not(.leaving)");
  entries.forEach((entry, n) => {
    entry.querySelector(".job-entry-title").textContent = `Job post ${n + 2}`;
  });
  const count = entries.length + 1;
  $("job-count").textContent = count > 1 ? `${count}/${maxJobs}` : "";
  $("add-job").disabled = count >= maxJobs;
  $("match").querySelector(".label").textContent = count > 1 ? `Match ${count} jobs` : "Match";
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
  // Each post is a few model calls on the user's own key, so a batch is asked first.
  if (
    aiReady &&
    !(await confirmDialog({
      title: `Match ${entries.length} job posts?`,
      text: "Each post is read and judged by the AI on your own API key, a few calls per post. Posts you matched before with this CV are skipped.",
      confirm: `Match ${entries.length} posts`,
    }))
  ) {
    return;
  }

  let cv = null;
  if (aiReady) cv = await currentCv();
  else saveCvText(cvText);

  $("result").hidden = true;
  $("upgrade-panel").hidden = true;
  const results = [];
  // Three steps a post with the AI match (read, judge, score), one without.
  const perPost = aiReady ? 3 : 1;
  const total = entries.length * perPost;
  progressBar(0, total);
  for (const [n, entry] of entries.entries()) {
    const step = `Job ${n + 1} of ${entries.length}: `;
    let ticks = 0;
    const tick = () => progressBar(n * perPost + Math.min(++ticks, perPost), total);
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
        const { job, scored } = await matchOne(text, url, cv, step, tick);
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
    progressBar((n + 1) * perPost, total);
    showBatch(results, entries.length);
  }
  progress("");
  const matched = results.filter((r) => r.result).length;
  const unsaved = results.filter((r) => r.save_error).length;
  const notSaved = unsaved ? ` ${unsaved} ${unsaved === 1 ? "wasn't" : "weren't"} saved.` : "";
  toast(`${matched} of ${entries.length} job posts matched.${notSaved}`, matched === entries.length && !unsaved ? "success" : "error");
  if (!aiReady) $("upgrade-panel").hidden = false;
  if (aiReady) await loadHistory();
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
    texts: { application: m.application_text, resume: m.resume_text, structured: Number(m.resume_structured) === 1 },
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
  // Rows already drawn are kept (and slide to their new place); a new one fades in.
  const list = $("batch-list");
  flip(list, () => {
    list.replaceChildren(
      ...ranked.map((r) => {
        if (!rows.has(r)) {
          const row = batchRow(r);
          rows.set(r, row);
          reveal([row]);
        }
        return rows.get(r);
      }),
    );
  });
}

// A score as a bar that grows to its width when drawn.
function bar(percent, tag) {
  const node = el("span", { className: `batch-bar ${tag}` });
  node.style.setProperty("--value", percent);
  return node;
}

// The drawn row of each batch result.
const rows = new WeakMap();

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
  const percent = Math.round(r.result.index * 100);
  const details = el("details", {}, [
    el("summary", { className: "batch-summary" }, [
      el("span", { className: `batch-score ${r.result.tag}` }, [`${percent}%`]),
      el("div", { className: "batch-head" }, [
        el("strong", {}, [heading]),
        el("div", { className: "muted" }, [
          el("span", { className: `tag ${r.result.tag}` }, [r.result.tag]),
          host ? ` · ${host}` : "",
          r.savedAt ? ` · matched before, ${new Date(r.savedAt * 1000).toLocaleDateString()}` : "",
        ]),
        bar(percent, r.result.tag),
      ]),
      el("span", { className: "chevron" }),
    ]),
    ...(r.savedAt
      ? [el("div", { className: "muted" }, [`${r.points} · not matched again`]), ...withFilter(savedSections(r.items))]
      : [el("div", { className: "muted" }, [scoreParts(r.result)]), ...withFilter(groupSections(r.groups, r.verdicts, r.result)), ...softSkills(r.soft_skills)]),
    ...(aiReady ? [el("div", { className: "batch-documents" }, documents(r.saved_id, r.texts ?? {}, r.save_error))] : []),
  ]);
  return el("li", { className: "batch-row" }, [details]);
}

// ---------- result ----------

function showResult(job, groups, verdicts, result, soft) {
  $("result").hidden = false;
  showScore(result.index, result.tag);
  $("job-heading").textContent = [job.job_title, job.company].filter(Boolean).join(" · ");
  $("score-parts").textContent = scoreParts(result);
  $("groups").replaceChildren(...withFilter(groupSections(groups, verdicts, result)));
  $("soft-skills").replaceChildren(...softSkills(soft));
  reveal($("result").querySelectorAll(".summary, .group, #soft-skills"));
}

// The score in its ring: the ring fills and the number counts up.
function showScore(index, tag) {
  const percent = Math.round(index * 100);
  const ring = $("score-ring");
  ring.className = `score-ring ${tag}`;
  ring.style.setProperty("--value", 0);
  requestAnimationFrame(() => requestAnimationFrame(() => ring.style.setProperty("--value", percent)));
  countUp($("score-value"), percent);
  $("score-tag").textContent = tag;
  $("score-tag").className = `tag ${tag}`;
}

// The verdict sections with buttons above them that show only the missing,
// partial or met requirements, with how many there are of each.
function withFilter(sections) {
  if (!sections.length) return sections;
  const list = el("div", { className: "verdicts" }, sections);
  const items = [...list.querySelectorAll(".group > ul > .item")];
  const count = (verdict) => items.filter((i) => i.classList.contains(verdict)).length;
  const choices = [
    ["all", "All", items.length],
    ["missing", "Missing", count("missing")],
    ["partial", "Partial", count("partial")],
    ["met", "Met", count("met")],
  ].filter(([key, , n]) => key === "all" || n > 0);
  const bar = el("div", { className: "filter" });
  bar.setAttribute("role", "group");
  bar.setAttribute("aria-label", "Show requirements");
  for (const [key, label, n] of choices) {
    const button = el("button", { type: "button", className: `filter-${key}` }, [label, el("span", { className: "count" }, [String(n)])]);
    button.setAttribute("aria-pressed", String(key === "all"));
    button.addEventListener("click", () => {
      for (const b of bar.children) b.setAttribute("aria-pressed", String(b === button));
      list.dataset.show = key;
      reveal(list.querySelectorAll(key === "all" ? ".group > ul > .item" : `.group > ul > .item.${key}`));
    });
    bar.append(button);
  }
  return [bar, list];
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

// A list that didn't load says so, with a retry, rather than "No saved
// matches yet". Never throws: a match that worked isn't turned into an error
// because the list beside it didn't refresh.
async function loadHistory() {
  let matches;
  try {
    ({ matches } = await request("GET", "history"));
  } catch (error) {
    const retry = el("button", { type: "button" }, ["Try again"]);
    retry.addEventListener("click", () => loadHistory());
    $("history-empty").hidden = true;
    $("history").replaceChildren(el("li", { className: "error" }, [`Couldn't load your saved matches: ${error.message} `, retry]));
    return;
  }
  $("history-empty").hidden = matches.length > 0;
  $("history").replaceChildren(
    ...matches.map((m) => {
      const when = new Date(m.created_at * 1000).toLocaleDateString();
      const written = [Number(m.has_application) && "application", Number(m.has_resume) && "résumé"].filter(Boolean);
      const tailored = m.cv_name?.endsWith("(tailored résumé)") ? ["tailored résumé"] : [];
      const meta = [`${Math.round(m.score * 100)}% ${m.tag}`, ...tailored, when, ...written].join(" · ");
      const button = el("button", { type: "button" }, [
        el("span", { className: "history-title" }, [[m.job_title, m.company].filter(Boolean).join(" · ") || "Untitled"]),
        el("span", { className: "history-meta" }, [meta]),
      ]);
      button.addEventListener("click", () => run(() => openSaved(m.id), button));
      return el("li", { className: `history-${m.tag}` }, [button]);
    }),
  );
  reveal($("history").children);
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
  showScore(Number(m.score), m.tag);
  $("job-heading").textContent = [m.job_title, m.company].filter(Boolean).join(" · ");
  $("score-parts").textContent = `${m.points}/${m.max_points} points · saved ${new Date(m.created_at * 1000).toLocaleString()}`;

  $("groups").replaceChildren(...withFilter(savedSections(m.items)));
  $("soft-skills").replaceChildren();
  reveal($("result").querySelectorAll(".summary, .group"));
  $("laya-block").hidden = true;
  $("laya-answer").hidden = true;
  $("job-url").value = m.job_url ?? "";
  $("job-text").value = m.job_text;
  markSteps();
  showDocuments(m.id, { application: m.application_text, resume: m.resume_text, structured: Number(m.resume_structured) === 1 });
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
  application: {
    title: "Job application",
    noun: "application",
    write: "Write application",
    endpoint: "write_application",
    busy: "Writing the application…",
    done: "Application written",
  },
  resume: {
    title: "Tailored résumé",
    noun: "résumé",
    write: "Tailor résumé",
    endpoint: "tailor_resume",
    busy: "Tailoring the résumé…",
    done: "Résumé tailored",
  },
};

function showDocuments(id, texts, saveError = "") {
  $("application-panel").hidden = false;
  $("documents").replaceChildren(...documents(id, texts, saveError));
  reveal([$("application-panel")]);
}

// The application and résumé boxes for a saved match: texts already written
// are shown, editable, with copy to clipboard.
function documents(id, texts, saveError = "") {
  if (!id) return [el("p", { className: "error" }, [saveError || "This match wasn't saved, so nothing can be written for it."])];
  // Notes the candidate adds go with both, read when a button is pressed.
  const notes = el("textarea", {
    rows: 3,
    maxLength: 2000,
    placeholder: "Optional: anything the AI should know or stress. E.g. \"I can start right away\", \"stress my Laravel work\", \"keep it short\".",
  });
  return [
    el("p", { className: "muted" }, ["Saved. Write a job application or a résumé tailored to this post, from your CV:"]),
    el("label", { className: "notes" }, ["Notes for the application and résumé", notes]),
    ...Object.keys(DOCUMENTS).map((kind) => documentBox(kind, id, texts[kind] ?? "", notes, kind === "resume" && texts.structured)),
  ];
}

// A tailored résumé written with fields (structured) gets its PDF laid out
// from them, so edits to the box aren't in it; the page says so first.
function documentBox(kind, id, written, notes, structured = false) {
  const doc = DOCUMENTS[kind];
  const state = { written, structured };
  const write = el("button", { type: "button" }, [el("span", { className: "label" }, [written ? "Write it again" : doc.write])]);
  const text = el("textarea", { rows: 18, hidden: !written, value: written });
  const copy = el("button", { type: "button", hidden: !written }, ["Copy to clipboard"]);
  const pdf = el("button", { type: "button", hidden: !written }, [el("span", { className: "label" }, ["Download PDF"])]);
  const status = el("span", { className: "muted", ariaLive: "polite" });
  // The post's keywords the résumé took over, shown after it is written.
  const keywords = el("div", { className: "keywords", hidden: true });
  // The tailored résumé's own match against the post, next to the CV's.
  const compare = el("p", { className: "compare", hidden: true });
  write.addEventListener("click", async () => {
    // Writing again replaces the text, edits included, and costs another model call.
    if (
      !text.hidden &&
      text.value.trim() &&
      !(await confirmDialog({
        title: `Write the ${doc.noun} again?`,
        text: `The new one replaces the text below, your edits included, and uses your API key.`,
        confirm: "Write it again",
      }))
    ) {
      return;
    }
    run(async () => {
      progress(doc.busy);
      progressBar(0);
      const answer = await post(doc.endpoint, { id, notes: notes.value.trim() });
      text.value = state.written = answer[kind];
      state.structured = Boolean(answer.resume_structured);
      text.hidden = copy.hidden = pdf.hidden = false;
      showKeywords(keywords, answer.keywords, answer.left_out);
      write.querySelector(".label").textContent = "Write it again";
      status.textContent = "";
      reveal([text]);
      if (kind === "resume") await compareResume(id, answer.resume, compare);
      progress("");
      await loadHistory();
      await flash("success", doc.done);
    }, write);
  });
  copy.addEventListener("click", () => copyText(text, status));
  pdf.addEventListener("click", async () => {
    if (
      state.structured &&
      text.value.trim() !== state.written.trim() &&
      !(await confirmDialog({
        title: "Your edits aren't in the PDF",
        text: "The PDF is made from the written résumé, so the changes you made in the box aren't in it. Add them to the notes and write it again, or download the PDF without them.",
        confirm: "Download without them",
      }))
    ) {
      return;
    }
    downloadPdf(kind, id, text, pdf);
  });
  // Copy sits next to Write, above the text, so it's in view on a phone.
  return el("div", { className: "document" }, [
    el("h4", {}, [doc.title]),
    el("div", { className: "actions" }, [write, copy, pdf, status]),
    keywords,
    compare,
    text,
  ]);
}

// The tailored résumé matched against the same post as a new saved match,
// shown next to the original CV's score. The post as read for the original
// match is reused when this page still has it, so the two are scored on the
// same requirements; otherwise it is read again.
async function compareResume(id, resumeText, line) {
  progress("Matching the tailored résumé against the post…");
  const saved = await request("GET", `saved/${id}`);
  const job = readJobs.get(id) ?? (await post("extract_job", { text: saved.job_text }));
  const profile = await post("extract_cv", { text: resumeText });
  const cv = { text: resumeText, profile: { ...profile, name: `${profile.name || saved.cv_name || "CV"} (tailored résumé)` } };
  const { scored } = await matchOne(saved.job_text, saved.job_url ?? "", cv, "Tailored résumé: ", () => {}, job);
  const before = Math.round(Number(saved.score) * 100);
  const after = Math.round(scored.result.index * 100);
  const open = el("button", { type: "button", className: "link-button" }, ["Open it"]);
  open.addEventListener("click", () => run(() => openSaved(scored.saved_id), open));
  line.replaceChildren(
    `The tailored résumé scores ${after}% ${scored.result.tag} against this post; your CV scored ${before}% ${saved.tag}. `,
    ...(scored.saved_id ? ["Saved as its own match. ", open] : [scored.save_error ?? ""]),
  );
  line.hidden = false;
}

// The textarea's current text as a PDF, edits included, saved as a download.
function downloadPdf(kind, id, area, button) {
  run(async () => {
    const response = await fetch(new URL("cv_match/pdf", base), {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id, kind, text: area.value }),
    });
    if (!response.ok || !response.headers.get("Content-Type")?.startsWith("application/pdf")) {
      const data = await response.json().catch(() => ({}));
      throw new Error(data.error ?? `The PDF couldn't be made: ${response.status}`);
    }
    const name = /filename\*=UTF-8''([^;]+)/.exec(response.headers.get("Content-Disposition") ?? "");
    const link = el("a", {
      href: URL.createObjectURL(await response.blob()),
      download: name ? decodeURIComponent(name[1]) : `${kind}.pdf`,
    });
    document.body.append(link);
    link.click();
    link.remove();
    setTimeout(() => URL.revokeObjectURL(link.href), 60000);
    toast("PDF downloaded.", "success");
  }, button);
}

// Which of the post's words the tailored résumé uses, each with the CV fact
// behind it, and which it left out because the CV doesn't show them.
function showKeywords(box, used = [], leftOut = []) {
  const parts = [];
  if (used?.length) {
    parts.push(
      el("details", {}, [
        el("summary", {}, [`Uses ${used.length} of the post's keywords: ${used.map((k) => k.term).join(", ")}`]),
        el("ul", {}, used.map((k) => el("li", {}, [el("strong", {}, [k.term]), k.basis ? `: from ${k.basis}` : ""]))),
      ]),
    );
  }
  if (leftOut?.length) {
    parts.push(el("p", { className: "muted" }, [`Left out, since your CV doesn't show it: ${leftOut.join(", ")}`]));
  }
  box.replaceChildren(...parts);
  box.hidden = !parts.length;
}

// The textarea's current text, so edits made on the page are what gets copied.
async function copyText(area, status) {
  status.classList.remove("error");
  try {
    await navigator.clipboard.writeText(area.value);
  } catch {
    // Older browsers or a page without clipboard permission: copy the
    // selection. setSelectionRange because select() alone selects nothing
    // on iOS.
    area.focus();
    area.setSelectionRange(0, area.value.length);
    let copied = false;
    try {
      copied = document.execCommand("copy");
    } catch {
      // Not supported either.
    }
    if (!copied) {
      status.textContent = "Couldn't copy. The text is selected: copy it yourself.";
      status.classList.add("error");
      return;
    }
  }
  status.textContent = "";
  toast("Copied to clipboard.", "success");
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
