// The CV match page: reads the CV and job post, has the server match them
// with the taxonomy, asks the language model about what the taxonomy can't
// decide, and shows the legacy ranking score. The matching and scoring are PHP (Cv_matcher.php);
// this file only reads files, calls the endpoints and draws the result.
//
// Without the AI match (not signed in, or signed in without an API key of
// their own) the page runs the free match instead: one quick_match call, no
// model, nothing saved. Signed in, a post can still be fetched from a link.
// A key switched off for evaluating job posts (aiUses.job_evaluation) still
// saves the match: the server reads both texts with the free reader and
// what the taxonomy can't decide counts as missing, with no model call.
// Switched off for the résumé or application, that box says so.
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
// An application or résumé being written, kept for this tab so the page can
// pick it up after a reload: {id, kind, before, started}, before being
// when it was last written (null if never), so a new one is told apart.
const PENDING_KEY = "cv_match.pending";
// How long a pending one is waited for after it was started: the model call
// has 180 seconds on the server, plus a margin.
const PENDING_MS = 4 * 60 * 1000;
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
// use => whether the user's key (or the server's) may be used for it.
let aiUses = { resume: false, application: false, job_evaluation: false };
let maxJobs = 10;
// The saved match the page was opened on, from its link (cv_match/{id}).
let openId = null;
// Job posts as read for each saved match this page made, by saved id, so a
// tailored résumé is scored on the same requirements as the CV was.
const readJobs = new Map();

export function start(baseUrl, state) {
  base = baseUrl;
  ({ signedIn, aiReady, aiUses, maxJobs, openId } = state);
  $("add-job").addEventListener("click", addJob);
  fileInput($("cv-file"), $("cv-drop"), $("cv-text"), $("cv-file-name"));
  fileInput($("job-file"), $("job-drop"), $("job-text"), $("job-file-name"));
  for (const id of ["cv-text", "job-text"]) $(id).addEventListener("input", markSteps);
  $("match").addEventListener("click", (e) => run(match, e.currentTarget));
  showJobCount();
  if (aiReady) showCv(loadCv());
  else startFree();
  markSteps();
  // A saved match's link opens it; its owner has to be signed in to see it.
  if (openId && (signedIn || aiReady)) run(async () => continuePending(await openSaved(openId)));
  else if (openId) progress("This link opens a saved match. Sign in to see it, and you'll come back to it.", true);
  if (!signedIn) return;
  watchReturn();
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
  watchHistory();
  watchApplicationDelivery();
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
    // A button switched off for good (a purpose the key is off for) stays off.
    for (const b of document.querySelectorAll("main button")) b.disabled = b.dataset.off === "true";
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
    const cv = JSON.parse(localStorage.getItem(CV_KEY));
    if (cv?.profile) cv.profile = withKindNames(cv.profile);
    return cv;
  } catch {
    return null;
  }
}

// CVs read before the lists were named by term kind (titles, skills, ...)
// are still saved in some browsers: give them the new names once.
function withKindNames(profile) {
  const renamed = { titles: "title", skills: "skill", languages: "language", certifications: "certificate" };
  const out = { ...profile };
  for (const [old, kind] of Object.entries(renamed)) {
    if (old in out) {
      out[kind] ??= out[old];
      delete out[old];
    }
  }
  return out;
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
  toast(`CV read: ${profile.skill.length} skills.`, "success");
  return cv;
}

function showCv(cv) {
  $("cv-input").open = !cv;
  if (!cv) return;
  const p = cv.profile;
  $("cv-status").textContent = `${p.name || "CV"}: ${p.experience_years} years, ${p.skill.length} skills. Read ${new Date(cv.read_at).toLocaleDateString()}.`;
  $("cv-profile").replaceChildren(...[...p.title, ...p.skill, ...p.language, ...p.certificate].map((s) => chip(s)));
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
  $("cv-status").textContent = `Found ${years}${profile.skill.length} skills and ${profile.title.length} job titles we know.`;
  const found = [...profile.title, ...profile.skill];
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
  const { job, profile, groups, verdicts, result, soft_skills, laya, laya_error, apply_instructions } = await post("quick_match", { job_text: jobText, cv_text: cvText });
  showFreeCv(cvText, profile);
  if (!Object.values(groups).some((items) => items.length)) {
    throw new Error("The free match found no skills it knows in this post. The AI match reads requirements written as sentences too.");
  }
  return { job: { ...job, job_title: job.job_title || job.heading }, groups, verdicts: new Map(Object.entries(verdicts)), result, soft_skills, laya, laya_error, apply: apply_instructions };
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
  const { job, groups, verdicts, result, soft_skills, laya, laya_error, apply } = await freeMatchOne(jobText, cvText);
  showResult(job, groups, verdicts, result, soft_skills, apply);
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
  showMatchUrl(null);
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
  showResult(job, groups, new Map(Object.entries(verdicts)), result, soft_skills, scored.apply_instructions);
  $("batch-result").hidden = true;
  // Laya's English checkpoint: both texts in English plus the verdicts.
  $("laya-block").hidden = false;
  $("laya").value = JSON.stringify({ job: job.text_en, cv: cv.profile.text_en || cv.text, summary: laya_summary }, null, 1);
  showLaya(scored.laya, scored.laya_error);
  showDocuments(scored.saved_id, {}, scored.save_error, scored.apply_instructions);
  showMatchUrl(scored.saved_id);
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

// One job post's text through the AI match: extracted here, then matched on
// the server (match): decided by the taxonomy, judged by the model where it
// can't, scored and saved. `step` prefixes the progress lines ("Job 2 of 5: ").
async function matchOne(text, url, cv, step = "", tick = () => {}, readJob = null) {
  progress(`${step}Reading the job post…`);
  const job = readJob ?? (await post("extract_job", { text }));

  tick();
  progress(
    aiUses.job_evaluation
      ? `${step}Matching: the taxonomy first, then the AI for what it can't decide…`
      : `${step}Matching with the taxonomy…`,
  );
  // Scored and saved in the same call, with the texts the application is written from.
  const scored = await post("match", {
    job,
    profile: cv.profile,
    job_text: text,
    job_url: url,
    cv_name: cv.profile.name,
    cv_text: cv.text,
  });
  tick();
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
    aiUses.job_evaluation &&
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
          apply: scored.apply_instructions,
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
    apply: m.apply_instructions,
    texts: savedTexts(m),
  };
}

// A saved match's application and résumé, as the document boxes take them.
function savedTexts(m) {
  return {
    application: m.application_text,
    resume: m.resume_text,
    structured: Number(m.resume_structured) === 1,
    writtenAt: { application: m.application_written_at, resume: m.resume_written_at },
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
          r.apply ? " · " : "",
          r.apply ? el("span", { className: `apply-chip${r.apply.trap ? " trap" : ""}` }, [r.apply.trap ? "Special instructions" : "Apply by email"]) : "",
        ]),
        bar(percent, r.result.tag),
      ]),
      el("span", { className: "chevron" }),
    ]),
    ...applyNotice(r.apply),
    ...(r.savedAt
      ? [el("div", { className: "muted" }, [`${r.points} · not matched again`]), ...withFilter(savedSections(r.items))]
      : [el("div", { className: "muted" }, [scoreParts(r.result)]), ...withFilter(groupSections(r.groups, r.verdicts, r.result)), ...softSkills(r.soft_skills)]),
    ...(aiReady ? [el("div", { className: "batch-documents" }, documents(r.saved_id, r.texts ?? {}, r.save_error, r.apply))] : []),
  ]);
  return el("li", { className: "batch-row" }, [details]);
}

// ---------- result ----------

function showResult(job, groups, verdicts, result, soft, apply = null) {
  $("result").hidden = false;
  showScore(result.index, result.tag);
  $("job-heading").textContent = [job.job_title, job.company].filter(Boolean).join(" · ");
  $("apply-notice").replaceChildren(...applyNotice(apply));
  $("score-parts").textContent = scoreParts(result);
  $("groups").replaceChildren(...withFilter(groupSections(groups, verdicts, result)));
  $("soft-skills").replaceChildren(...softSkills(soft));
  reveal($("result").querySelectorAll(".summary, .apply-notice, .group, #soft-skills"));
}

// What the post asks of applicants beyond a click: send it by email, with a
// subject line, a keyword or answers to questions. Often a filter for people
// who only press Easy Apply, so it is shown above the requirements, quoting
// the post. Nothing when the post asks for nothing special.
const APPLY_ASKS = {
  subject: "a specific subject line",
  keyword: "a specific word or phrase",
  questions: "answers to its questions",
  no_easy_apply: "no one-click applications",
  proof: "proof that you read the post",
};

function applyNotice(apply) {
  if (!apply) return [];
  const heading = apply.trap
    ? "Read this before you apply: the post asks for more than a click"
    : "This post asks for applications by email";
  const facts = [
    apply.email && el("li", {}, ["Send it to ", el("a", { href: mailto(apply) }, [apply.email])]),
    apply.subject && el("li", {}, ["Subject line: ", el("code", {}, [apply.subject])]),
    apply.keyword && !apply.subject && el("li", {}, ["Include: ", el("code", {}, [apply.keyword])]),
    apply.asks.length > 0 && el("li", {}, [`It asks for ${apply.asks.map((ask) => APPLY_ASKS[ask]).join(", ")}.`]),
  ].filter(Boolean);
  return [
    el("section", { className: `apply-notice${apply.trap ? " trap" : ""}` }, [
      el("h3", {}, [heading]),
      ...(apply.trap
        ? [el("p", { className: "muted" }, [`Posts like this often skip applications that don't follow it, such as Easy Apply.${aiReady ? " Write application below follows it for you." : ""}`])]
        : []),
      el("ul", {}, facts),
      el("blockquote", {}, apply.lines.map((line) => el("p", {}, [line]))),
    ]),
  ];
}

// A mailto link with the subject the post asks for filled in.
function mailto(apply) {
  return `mailto:${apply.email}${apply.subject ? `?subject=${encodeURIComponent(apply.subject)}` : ""}`;
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

// The list comes with the page; Trongate MX polls cv_match/history_list into
// the hidden #history-latest every 30 seconds while the page is in view, so
// matches saved elsewhere (a job search, a tool posting to its API) show up
// on their own. Coming back to the page (another tab, a phone waking, the
// back/forward cache) reads it at once. cvMatchHistory puts an answer in the
// list when it changed, revealing the new rows.
let shownHistory = "";

function watchHistory() {
  shownHistory = $("history").innerHTML;
  localDates($("history"));
  reveal($("history").children);
  $("history").addEventListener("click", (e) => {
    const button = e.target.closest("button[data-id]");
    if (button) run(() => openSaved(Number(button.dataset.id)), button);
  });
  window.cvMatchHistory = showHistory;
  let readAt = 0;
  const readOnReturn = () => {
    if (Date.now() - readAt < 2000) return;
    readAt = Date.now();
    loadHistory();
  };
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) TrongateMX.stopPolling($("history-latest"));
    else readOnReturn();
  });
  window.addEventListener("pageshow", (e) => {
    if (e.persisted && !document.hidden) readOnReturn();
  });
  // Trongate MX starts the polling once the page has loaded; a page opened
  // in a background tab waits until it's looked at.
  document.addEventListener("DOMContentLoaded", () => {
    if (document.hidden) TrongateMX.stopPolling($("history-latest"));
  });
}

// The list read now (MX takes a click while it isn't polling), then polled
// again. The answer arrives in cvMatchHistory.
function loadHistory() {
  const poller = $("history-latest");
  if (!poller) return;
  TrongateMX.stopPolling(poller);
  poller.click();
  TrongateMX.startPolling(poller, "30s");
}

// The server writes dates in its own time zone; a match saved just after
// midnight here is shown on the day it was saved here.
function localDates(list) {
  for (const time of list.querySelectorAll("time[datetime]")) time.textContent = new Date(time.dateTime).toLocaleDateString();
}

function showHistory() {
  const poller = $("history-latest");
  const latest = poller.querySelector(".history-latest ul");
  poller.replaceChildren();
  if (!latest || latest.innerHTML === shownHistory) return;
  const shown = new Set([...$("history").querySelectorAll("button[data-id]")].map((b) => b.dataset.id));
  shownHistory = latest.innerHTML;
  $("history").replaceChildren(...latest.children);
  localDates($("history"));
  // A row that arrives while a task runs is as off as the rest (run()).
  const working = document.body.classList.contains("working");
  for (const b of $("history").querySelectorAll("button")) b.disabled = working;
  $("history-empty").hidden = $("history").children.length > 0;
  reveal([...$("history").children].filter((li) => !shown.has(li.querySelector("button[data-id]")?.dataset.id)));
}

// A saved match shown like a fresh one, from its stored verdicts.
async function openSaved(id) {
  progress("Opening the saved match…");
  const m = await request("GET", `saved/${id}`);
  showSaved(m);
  progress("");
  return m;
}

function showSaved(m) {
  $("result").hidden = false;
  $("batch-result").hidden = true;
  showScore(Number(m.score), m.tag);
  $("job-heading").textContent = [m.job_title, m.company].filter(Boolean).join(" · ");
  $("score-parts").textContent = `${m.points}/${m.max_points} points · saved ${new Date(m.created_at * 1000).toLocaleString()}`;
  $("apply-notice").replaceChildren(...applyNotice(m.apply_instructions));

  $("groups").replaceChildren(...withFilter(savedSections(m.items)));
  $("soft-skills").replaceChildren();
  reveal($("result").querySelectorAll(".summary, .apply-notice, .group"));
  $("laya-block").hidden = true;
  $("laya-answer").hidden = true;
  $("job-url").value = m.job_url ?? "";
  $("job-text").value = m.job_text;
  markSteps();
  showMatchUrl(m.id);
  showDocuments(m.id, savedTexts(m), "", m.apply_instructions);
  $("result").scrollIntoView({ behavior: "smooth" });
}

// The address bar follows the match on show: cv_match/{id} for a saved match,
// so it can be copied and linked to, plain cv_match otherwise. Replaced, not
// pushed, so Back still leaves the page.
function showMatchUrl(id) {
  const url = new URL(id ? `cv_match/${id}` : "cv_match", base);
  if (url.pathname !== location.pathname) history.replaceState(null, "", url);
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

function showDocuments(id, texts, saveError = "", apply = null) {
  $("application-panel").hidden = false;
  $("documents").replaceChildren(...documents(id, texts, saveError, apply));
  reveal([$("application-panel")]);
}

// The application and résumé boxes for a saved match: texts already written
// are shown, editable, with copy to clipboard.
function documents(id, texts, saveError = "", apply = null) {
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
    ...Object.keys(DOCUMENTS).map((kind) => documentBox(kind, id, texts[kind] ?? "", notes, kind === "resume" && texts.structured, apply, texts.writtenAt?.[kind] ?? null)),
    applyButton(id),
    joblogBox(id),
  ];
}

// ---------- Apply ----------

// The Apply dialog (#application-delivery in views/cv_match.php): its
// content is a fragment the server prints (views/application_delivery.php),
// read on every opening so a newly written application counts: how the post
// wants the application delivered, by email or on the employer's site, with
// fields to copy and the PDFs to attach or upload. The body is the
// application box's text, edits included: the box next to the button (the
// open match's, or a batch row's), whose boxes the dialog's PDF buttons use too.
let deliveryDocuments = null;

function applyButton(id) {
  const button = el("button", { type: "button" }, [el("span", { className: "label" }, ["Apply"])]);
  button.addEventListener("click", () =>
    run(async () => {
      const response = await fetch(new URL(`cv_match/application_delivery/${id}`, base));
      if (!response.headers.get("Content-Type")?.startsWith("text/html")) {
        const data = await response.json().catch(() => ({}));
        throw new Error(data.error ?? `The Apply dialog couldn't be opened: ${response.status}`);
      }
      $("application-delivery-body").innerHTML = await response.text();
      deliveryDocuments = documentsOf(button);
      const box = deliveryDocuments.querySelector('.document[data-kind="application"] textarea');
      if (box && !box.hidden && box.value.trim()) $("delivery-body").value = box.value;
      $("application-delivery").showModal();
    }, button),
  );
  return el("div", { className: "actions apply-open" }, [button]);
}

// The dialog's copy and PDF buttons, and a click on the backdrop closing it.
function watchApplicationDelivery() {
  const dialog = $("application-delivery");
  if (!dialog) return;
  dialog.addEventListener("click", (e) => {
    if (e.target === dialog) return dialog.close();
    const copy = e.target.closest("button[data-copy]");
    if (copy) return copyText($(copy.dataset.copy), $("application-delivery-status"));
    const pdf = e.target.closest("button[data-pdf]");
    if (pdf) downloadMatchPdf(Number(dialog.querySelector(".application-delivery").dataset.id), pdf.dataset.pdf, pdf, deliveryDocuments);
  });
}

// ---------- Jobnet joblog ----------

// The match as an entry for Jobnet's joblog ("Opret joblog"): every field in
// Jobnet's order and words, filled in on the server (Joblog.php), each with
// its own copy button, and the three PDFs Jobnet keeps. Read when opened, and
// again on every opening, so a newly written application counts.
function joblogBox(id) {
  const body = el("div", { className: "joblog-body" });
  const box = el("details", { className: "joblog" }, [
    el("summary", {}, ["Jobnet joblog"]),
    el("p", { className: "muted" }, [
      "Jobnet's \"Opret joblog\" form, filled in from this match: check each field, copy it over, and download the PDFs for its uploads. Empty fields weren't in the post.",
    ]),
    body,
  ]);
  box.addEventListener("toggle", () => {
    if (!box.open) return;
    body.replaceChildren(el("p", { className: "muted" }, ["Filling in the joblog…"]));
    request("GET", `joblog/${id}`)
      .then(withCvrAddress)
      .then((entry) => {
        const saved = joblogSaved(id, entry);
        body.replaceChildren(...entry.sections.map(joblogSection), joblogFiles(id, entry.files), joblogPrompt(body), saved.section);
        reveal([body]);
      })
      .catch((error) => body.replaceChildren(el("p", { className: "error" }, [error.message])));
  });
  return box;
}

// When the post names no postcode and town, the company's address in the
// CVR register (cvrapi.dk) fills them in, marked as such: it is the
// registered address, which may not be where the job is. The browser asks
// cvrapi.dk itself, as company sign-up does, so lookups count against the
// visitor's own address, not the server's quota. Only a company whose name
// shares most words with the post's is taken; a failed lookup changes nothing.
const CVRAPI = "https://cvrapi.dk/api";
// A slow register must not hold up the joblog: after this, it is shown without.
const CVR_TIMEOUT_MS = 4000;
// "A/S", "ApS" and the like at the end of a name: the register writes them its own way.
const LEGAL_FORM = /\s+(?:a\/s|aps|i\/s|p\/s|k\/s|ivs|amba|a\.m\.b\.a\.)\.?$/i;

async function withCvrAddress(entry) {
  const fields = Object.fromEntries(entry.sections.flatMap((section) => section.fields).map((field) => [field.name, field]));
  const company = fields.name?.value ?? "";
  if (!fields.postalCodeAndCity || fields.postalCodeAndCity.value !== "" || company === "") return entry;
  let found;
  try {
    const response = await fetch(`${CVRAPI}?${new URLSearchParams({ search: company.replace(LEGAL_FORM, ""), country: "dk" })}`, { headers: { Accept: "application/json" }, signal: AbortSignal.timeout(CVR_TIMEOUT_MS) });
    found = await response.json();
  } catch {
    return entry;
  }
  if (found?.error || !found?.zipcode || !found?.city || !sameCompany(company, String(found.name ?? ""))) return entry;
  const note = `From the CVR register (${found.name}): the registered address, check that it's where the job is`;
  fields.postalCodeAndCity.value = `${found.zipcode} ${found.city}`;
  fields.postalCodeAndCity.hint = note;
  if (fields.companyAddress && fields.companyAddress.value === "" && found.address) {
    fields.companyAddress.value = found.address;
    fields.companyAddress.hint = note;
  }
  if (fields.companyCountry && fields.companyCountry.value === "") fields.companyCountry.value = "Danmark";
  if (fields.unknownPostalOrCity) fields.unknownPostalOrCity.value = false;
  return entry;
}

// Whether a register name is the post's company: most of the post's words
// in it, legal forms aside ("Nordisk Fragt A/S" and "NORDISK FRAGT ApS").
function sameCompany(wanted, name) {
  const words = (text) => text.toLowerCase().split(/[^\p{L}\d]+/u).filter((word) => word.length >= 2 && !["as", "aps", "is", "ps", "ks", "ivs", "amba", "a", "s"].includes(word));
  const want = words(wanted);
  const have = new Set(words(name));
  return want.length > 0 && want.filter((word) => have.has(word)).length / want.length >= 0.5;
}

function joblogSection(section) {
  return el("section", { className: "joblog-section" }, [el("h5", {}, [section.heading]), ...section.fields.map(joblogField)]);
}

function joblogField(field) {
  const label = el("span", { className: "joblog-label" }, [field.label, field.required ? el("span", { className: "required", ariaHidden: "true" }, ["*"]) : ""]);
  const hint = field.hint ? el("small", { className: "muted" }, [field.hint]) : "";
  // A choice is shown with its options, the one to pick marked; Jobnet's
  // radio buttons and checkbox can't be pasted into.
  if (field.type === "radio" || field.type === "checkbox") {
    const options = field.type === "checkbox" ? { yes: "Ja", no: "Nej" } : field.options;
    const chosen = field.type === "checkbox" ? (field.value ? "yes" : "no") : field.value;
    const value = field.type === "checkbox" ? () => (field.value ? "tick it" : "leave it unticked") : () => `choose "${options[field.value] ?? ""}"`;
    // A choice the candidate makes here (field.choose, how far they are with
    // the job) is a row of buttons, saved when pressed.
    const chips = Object.entries(options).map(([value, text]) =>
      field.choose
        ? el("button", { type: "button", className: value === chosen ? "chip chosen" : "chip", ariaPressed: String(value === chosen) }, [text])
        : el("span", { className: value === chosen ? "chip chosen" : "chip" }, [text]),
    );
    if (field.choose) {
      Object.keys(options).forEach((value, i) =>
        chips[i].addEventListener("click", async () => {
          if (!(await field.choose(value))) return;
          field.value = value;
          chips.forEach((chip, j) => {
            chip.className = j === i ? "chip chosen" : "chip";
            chip.ariaPressed = String(j === i);
          });
        }),
      );
    }
    return joblogEntry(field, value, [label, el("div", { className: "joblog-options" }, chips), hint]);
  }
  const input =
    field.type === "textarea"
      ? el("textarea", { rows: 4, maxLength: 2000, value: field.value })
      : el("input", { type: "text", value: field.value, inputMode: field.type === "number" ? "numeric" : "text" });
  input.setAttribute("aria-label", field.label);
  const status = el("span", { className: "muted", ariaLive: "polite" });
  const copy = el("button", { type: "button" }, ["Copy"]);
  copy.addEventListener("click", () => copyText(input, status));
  const left = field.type === "textarea" ? el("small", { className: "muted" }) : "";
  const count = () => left && (left.textContent = `Antal tegn tilbage: ${2000 - input.value.length}`);
  input.addEventListener("input", count);
  count();
  return joblogEntry(field, () => input.value.trim(), [
    label,
    el("div", { className: "joblog-value" }, [input, copy]),
    hint,
    left,
    status,
  ]);
}

// What the candidate tells Recruiter about the entry, saved with the match
// (cv_match/joblog_status): how far they are with the job, picked on the
// status field above, and whether they've entered it in their Jobnet joblog.
// The status field gets its save here, so it must run before the sections render.
function joblogSaved(id, entry) {
  const status = entry.sections.flatMap((section) => section.fields).find((field) => field.name === "searchStatus");
  const state = { logged: entry.logged_at !== null };
  const note = el("span", { className: "muted", ariaLive: "polite" });
  const box = el("input", { type: "checkbox", checked: state.logged });
  const when = (at) => (at ? `Entered on ${new Date(at * 1000).toLocaleDateString()}.` : "");
  note.textContent = when(entry.logged_at);
  const save = async (searchStatus, logged) => {
    try {
      const saved = await post("joblog_status", { id, status: searchStatus, logged });
      state.logged = saved.logged_at !== null;
      note.textContent = when(saved.logged_at);
      loadHistory();
      return true;
    } catch (error) {
      toast(`Couldn't save it: ${error.message}`, "error");
      return false;
    }
  };
  if (status) {
    status.choose = (value) => save(value, state.logged);
    status.hint = "Pick it here as you get along: it's saved with the match.";
  }
  box.addEventListener("change", async () => {
    if (!(await save(status?.value ?? "IkkeSoegt", box.checked))) box.checked = !box.checked;
  });
  return {
    section: el("section", { className: "joblog-section" }, [
      el("h5", {}, ["In your Jobnet joblog"]),
      el("label", { className: "joblog-logged" }, [box, " I've entered this job in my joblog on Jobnet"]),
      note,
    ]),
  };
}

// A field's row, knowing its label and its current value (edits included)
// for the AI prompt.
function joblogEntry(field, value, children) {
  const node = el("div", { className: "joblog-field" }, children);
  node.joblog = { label: field.label, value };
  return node;
}

// The fallback for when no extension fills the form: a prompt for an AI that
// drives the browser (Claude in Chrome and the like) with every value as the
// panel shows it now, edits included. It stops before saving, and leaves the
// PDFs to the candidate, since such an agent can't pick files on the computer.
function joblogPrompt(body) {
  const status = el("span", { className: "muted", ariaLive: "polite" });
  const copy = el("button", { type: "button" }, ["Copy as AI prompt"]);
  copy.addEventListener("click", () => {
    const lines = [];
    for (const section of body.querySelectorAll(".joblog-section")) {
      const fields = [...section.querySelectorAll(".joblog-field")].map((node) => node.joblog);
      if (!fields.length) continue;
      lines.push("", `${section.querySelector("h5").textContent}:`);
      for (const f of fields) {
        const value = f.value();
        if (value === "") lines.push(`- ${f.label}: leave empty`);
        else if (value.includes("\n")) lines.push(`- ${f.label}:`, ...value.split("\n").map((line) => `    ${line}`));
        else lines.push(`- ${f.label}: ${value}`);
      }
    }
    const prompt = [
      "Fill in a new entry in my joblog on Jobnet (jobnet.dk, \"Opret joblog\"). I am signed in. Use exactly these values, field by field, in Jobnet's order.",
      "Stilling eller arbejdsområde, Virksomhedens navn and Postnummer og by only keep a value picked from Jobnet's suggestions; typed text is cleared. Type the value and pick the matching suggestion; if none shows, search for a shorter part (\"Backend\" for \"Backend-udvikler\" finds \"Softwareudvikler, backend\"), pick the closest and tell me which you picked.",
      "Antal timer pr. uge only shows for Deltid. Ansøgningsfrist is typed as dd-mm-yyyy.",
      ...lines,
      "",
      "Don't upload any files: I'll add the application, CV and job ad PDFs myself. Don't press Gem: stop when the form is filled and tell me what you couldn't fill.",
    ].join("\n");
    area.value = prompt;
    area.hidden = false;
    copyText(area, status);
  });
  // Shown once copied, so the prompt can be read, and copied by hand if the
  // clipboard refuses.
  const area = el("textarea", { rows: 8, readOnly: true, hidden: true });
  return el("section", { className: "joblog-section" }, [
    el("h5", {}, ["Fill it in with an AI"]),
    el("p", { className: "muted" }, ["Copies these values as instructions for an AI that can use your browser, such as Claude in Chrome. It fills the form and stops before Gem; you add the PDFs."]),
    el("div", { className: "actions" }, [copy, status]),
    area,
  ]);
}

// Jobnet's "Gem ansøgning, CV og jobannonce": the application, CV (the
// tailored résumé once there is one) and job post as PDFs. The application
// and résumé are the texts in their boxes above, edits included.
function joblogFiles(id, files) {
  return el("section", { className: "joblog-section" }, [
    el("h5", {}, ["Gem ansøgning, CV og jobannonce"]),
    el(
      "div",
      { className: "actions" },
      files.map((file) => {
        // Not disabled, since run() enables every button when a task ends.
        const button = el("button", { type: "button", className: file.ready ? "" : "unavailable", title: file.ready ? (file.note ?? "") : file.missing }, [
          el("span", { className: "label" }, [`${file.label} (PDF)`]),
        ]);
        button.addEventListener("click", () => {
          if (!file.ready) return toast(file.missing, "error");
          downloadMatchPdf(id, file.kind, button, documentsOf(button));
        });
        return button;
      }),
    ),
    ...files.filter((file) => !file.ready).map((file) => el("p", { className: "muted" }, [`${file.label}: ${file.missing}`])),
  ]);
}

// A tailored résumé written with fields (structured) gets its PDF laid out
// from them, so edits to the box aren't in it; the page says so first.
function documentBox(kind, id, written, notes, structured = false, apply = null, writtenAt = null) {
  const doc = DOCUMENTS[kind];
  const state = { written, structured };
  const write = el("button", { type: "button", disabled: !aiUses[kind] }, [el("span", { className: "label" }, [written ? "Write it again" : doc.write])]);
  write.dataset.off = String(!aiUses[kind]);
  const text = el("textarea", { rows: 18, hidden: !written, value: written });
  const copy = el("button", { type: "button", hidden: !written }, ["Copy to clipboard"]);
  const pdf = el("button", { type: "button", className: "download-pdf", hidden: !written }, [el("span", { className: "label" }, ["Download PDF"])]);
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
      setPending({ id, kind, before: writtenAt, started: Date.now() });
      let answer;
      try {
        answer = await post(doc.endpoint, { id, notes: notes.value.trim() });
      } catch (error) {
        // A dropped connection (phone locked, tab in the background) leaves
        // it pending: the server finishes it and the page picks it up.
        if (!(error instanceof TypeError)) clearPending();
        throw error;
      }
      text.value = state.written = answer[kind];
      writtenAt = answer.written_at;
      state.structured = Boolean(answer.resume_structured);
      text.hidden = copy.hidden = pdf.hidden = false;
      showKeywords(keywords, answer.keywords, answer.left_out);
      write.querySelector(".label").textContent = "Write it again";
      status.textContent = "";
      reveal([text]);
      if (kind === "resume") await compareResume(id, answer.resume, compare);
      clearPending();
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
  // Where the post wants the application sent, next to the application.
  const sendTo =
    kind === "application" && apply?.email
      ? el("p", { className: "apply-hint" }, [
          "The post asks for it by email: ",
          el("a", { href: mailto(apply) }, [apply.email]),
          apply.subject ? [" with the subject ", el("code", {}, [apply.subject])] : "",
          ".",
        ].flat())
      : "";
  const off = aiUses[kind]
    ? ""
    : el("p", { className: "muted" }, [
        `Your API key is switched off for writing the ${doc.noun}. `,
        el("a", { href: new URL("account", base).href }, ["Switch it on"]),
        " on your account page.",
      ]);
  const box = el("div", { className: "document" }, [
    el("h4", {}, [doc.title]),
    sendTo,
    off,
    el("div", { className: "actions" }, [write, copy, pdf, status]),
    keywords,
    compare,
    text,
  ]);
  box.dataset.kind = kind;
  return box;
}

// The tailored résumé matched against the same post as a new saved match,
// shown next to the original CV's score. The post as read for the original
// match is reused when this page still has it, so the two are scored on the
// same requirements; otherwise it is read again.
async function compareResume(id, resumeText, line) {
  progress("Matching the tailored résumé against the post…");
  const saved = await request("GET", `saved/${id}`);
  // Already matched when the page was reloaded after it.
  const earlier = await post("already_matched", { cv_text: resumeText, job_text: saved.job_text });
  if (earlier.match) return showComparison(saved, earlier.match.id, Number(earlier.match.score), earlier.match.tag, "", line);
  const job = readJobs.get(id) ?? (await post("extract_job", { text: saved.job_text }));
  const profile = await post("extract_cv", { text: resumeText });
  const cv = { text: resumeText, profile: { ...profile, name: `${profile.name || saved.cv_name || "CV"} (tailored résumé)` } };
  const { scored } = await matchOne(saved.job_text, saved.job_url ?? "", cv, "Tailored résumé: ", () => {}, job);
  showComparison(saved, scored.saved_id, scored.result.index, scored.result.tag, scored.save_error ?? "", line);
}

function showComparison(saved, savedId, score, tag, saveError, line) {
  const before = Math.round(Number(saved.score) * 100);
  const after = Math.round(score * 100);
  const open = el("button", { type: "button", className: "link-button" }, ["Open it"]);
  open.addEventListener("click", () => run(() => openSaved(savedId), open));
  line.replaceChildren(
    `The tailored résumé scores ${after}% ${tag} against this post; your CV scored ${before}% ${saved.tag}. `,
    ...(savedId ? ["Saved as its own match. ", open] : [saveError]),
  );
  line.hidden = false;
}

// ---------- coming back to a pending application or résumé ----------

// Writing takes a while, and a phone that is locked or switches apps
// meanwhile drops the request. The server finishes and saves it anyway, so
// when the page comes back while one is pending it reloads on the saved
// match and continues there (continuePending).
function watchReturn() {
  let hiddenAt = null;
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) {
      hiddenAt = Date.now();
      return;
    }
    const away = hiddenAt === null ? 0 : Date.now() - hiddenAt;
    hiddenAt = null;
    if (pending() && away > 3000) reloadOnPending();
  });
  // Restored from the back/forward cache: its request is gone. A button
  // switched off for the key may be on again by now (the account page is
  // where its link goes), so the page is read again; the address is the
  // saved match's own.
  window.addEventListener("pageshow", (e) => {
    if (!e.persisted) return;
    if (pending()) reloadOnPending();
    else if (document.querySelector('main button[data-off="true"]')) location.reload();
  });
}

function reloadOnPending() {
  history.replaceState(null, "", new URL(`cv_match/${pending().id}`, base));
  location.reload();
}

// After the page opened a saved match: if an application or résumé for it
// is pending, scroll to it and wait until the server has saved it, then show
// it (and match a tailored résumé against the post, if that didn't happen).
async function continuePending(m) {
  const p = pending();
  if (!p || p.id !== m.id) return;
  const doc = DOCUMENTS[p.kind];
  const box = () => $("documents").querySelector(`.document[data-kind="${p.kind}"]`);
  box()?.scrollIntoView({ behavior: "smooth", block: "start" });
  let saved = m;
  const written = (match) => match[`${p.kind}_written_at`] && String(match[`${p.kind}_written_at`]) !== String(p.before);
  while (!written(saved)) {
    if (Date.now() - p.started > PENDING_MS) {
      clearPending();
      throw new Error(`The ${doc.noun} didn't come back from the model. Press ${doc.write} to try again.`);
    }
    progress(`Still ${doc.busy.charAt(0).toLowerCase()}${doc.busy.slice(1)} It carries on if you leave the page.`);
    await new Promise((resolve) => setTimeout(resolve, 5000));
    saved = await request("GET", `saved/${m.id}`);
  }
  if (saved !== m) {
    showDocuments(saved.id, savedTexts(saved), "", saved.apply_instructions);
    box()?.scrollIntoView({ behavior: "smooth", block: "start" });
  }
  if (p.kind === "resume") await compareResume(saved.id, saved.resume_text, box().querySelector(".compare"));
  clearPending();
  progress("");
  await loadHistory();
  await flash("success", doc.done);
}

function pending() {
  try {
    return JSON.parse(sessionStorage.getItem(PENDING_KEY) ?? "null");
  } catch {
    return null;
  }
}

function setPending(p) {
  try {
    sessionStorage.setItem(PENDING_KEY, JSON.stringify(p));
  } catch {
    // Without storage the page just can't pick it up after a reload.
  }
}

function clearPending() {
  try {
    sessionStorage.removeItem(PENDING_KEY);
  } catch {}
}

// One of a saved match's PDFs from outside its box (the joblog, the Apply
// dialog); a button marked unavailable says why it can't be had yet. The
// box's own PDF button is used when it shows, so the text is the box's,
// edits included, and a tailored résumé warns when the edits won't be in it.
function downloadMatchPdf(id, kind, button, documents) {
  if (button.classList.contains("unavailable")) return toast(button.title, "error");
  const own = documents?.querySelector(`.document[data-kind="${kind}"] button.download-pdf`);
  if (own && !own.hidden) return own.click();
  downloadPdf(kind, id, { value: "" }, button);
}

// The application and résumé boxes a node sits next to: the open match's
// (#documents) or a batch row's (.batch-documents).
function documentsOf(node) {
  return node.closest("#documents, .batch-documents");
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
