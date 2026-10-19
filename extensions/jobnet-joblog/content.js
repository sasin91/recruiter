// On Jobnet: a small panel next to the "Opret joblog" form that fills it in
// from a saved match in Recruiter's CV checker (cv_match/joblog/{id}, see
// modules/cv_match/Joblog.php), PDFs included. It never presses Gem: the
// person checks the form and saves it.
//
// Jobnet's form is React, which keeps its own copy of each value and ignores
// values set from outside. So a value is set through the element's own
// prototype setter and announced with an input event, as typing would; radio
// buttons and the checkbox are clicked; a field with suggestions (job title,
// company, postcode and town) is typed into and its matching suggestion
// picked; the PDFs are attached to the upload fields with a DataTransfer.

(() => {
  const api = globalThis.browser ?? globalThis.chrome;

  // Jobnet's upload fields by the PDF that goes in them.
  const UPLOADS = { application: "file-Jobansoeninger", cv: "file-CV", resume: "file-CV", job: "file-Jobannoncer" };
  // Fields that list suggestions as you type.
  const TYPEAHEADS = ["occupation", "name", "postalCodeAndCity"];
  const ROOT_ID = "recruiter-joblog";

  // The form appears after Jobnet's page has loaded and goes when it leaves
  // it (one page app), so the panel follows it.
  new MutationObserver(watch).observe(document.documentElement, { childList: true, subtree: true });
  watch();

  function form() {
    return document.querySelector('input[name="occupation"]')?.closest("form") ?? null;
  }

  function watch() {
    const present = document.getElementById(ROOT_ID);
    if (form() && !present) document.body.append(panel());
    if (!form() && present) present.remove();
  }

  // ---------- the panel ----------

  function panel() {
    const host = document.createElement("div");
    host.id = ROOT_ID;
    // A shadow root keeps Jobnet's styles out of the panel and the panel's out of Jobnet.
    const shadow = host.attachShadow({ mode: "open" });
    shadow.innerHTML = `
      <style>
        :host { all: initial; }
        .box { position: fixed; right: 16px; bottom: 16px; z-index: 2147483647; width: 320px; max-width: calc(100vw - 32px);
               font: 14px/1.4 system-ui, sans-serif; color: #1d1d1b; background: #fff; border: 1px solid #d6d6cf;
               border-radius: 12px; box-shadow: 0 8px 32px rgb(0 0 0 / 0.18); padding: 12px; }
        h2 { font-size: 15px; margin: 0 0 8px; display: flex; justify-content: space-between; }
        select, button { font: inherit; width: 100%; box-sizing: border-box; padding: 8px; border-radius: 8px; border: 1px solid #d6d6cf; background: #fff; color: inherit; }
        button { margin-top: 8px; cursor: pointer; }
        button.primary { background: #2f5bd3; border-color: #2f5bd3; color: #fff; font-weight: 600; }
        button:disabled { opacity: 0.6; cursor: wait; }
        .close { width: auto; margin: 0; padding: 0 6px; border: 0; background: none; font-size: 18px; line-height: 1; }
        ul { margin: 8px 0 0; padding-left: 18px; max-height: 40vh; overflow: auto; }
        li.miss { color: #a32c2c; }
        li.ok { color: #1f7a4d; }
        p { margin: 8px 0 0; color: #6b6b66; }
      </style>
      <div class="box" role="region" aria-label="Recruiter: Jobnet joblog">
        <h2>Fill from Recruiter <button class="close" type="button" aria-label="Close">×</button></h2>
        <select aria-label="Saved match"><option>Loading your saved matches…</option></select>
        <button class="primary fill" type="button" disabled>Fill in the form</button>
        <p class="note">Check the form afterwards, then press Gem yourself.</p>
        <ul class="log" hidden></ul>
      </div>`;
    const $ = (selector) => shadow.querySelector(selector);
    $(".close").addEventListener("click", () => host.remove());

    ask({ type: "matches" })
      .then((matches) => {
        if (!matches.length) throw new Error("No saved matches yet: match a job post in Recruiter's CV checker first.");
        $("select").replaceChildren(
          ...matches.map((m) => {
            const option = document.createElement("option");
            option.value = m.id;
            option.textContent = [m.job_title, m.company].filter(Boolean).join(" · ") || `Match ${m.id}`;
            return option;
          }),
        );
        $(".fill").disabled = false;
      })
      .catch((error) => {
        $("select").replaceChildren(new Option("Couldn't load your matches"));
        log($(".log"), error.message, false);
      });

    $(".fill").addEventListener("click", async () => {
      const button = $(".fill");
      button.disabled = true;
      button.textContent = "Filling in…";
      $(".log").replaceChildren();
      try {
        await fill(Number($("select").value), (text, ok) => log($(".log"), text, ok));
      } catch (error) {
        log($(".log"), error.message, false);
      }
      button.disabled = false;
      button.textContent = "Fill in the form";
    });
    return host;
  }

  function log(list, text, ok) {
    const item = document.createElement("li");
    item.className = ok ? "ok" : "miss";
    item.textContent = text;
    list.append(item);
    list.hidden = false;
  }

  // Firefox's browser.runtime.sendMessage returns a promise and takes no
  // callback; Chrome and Edge answer through the callback.
  async function ask(message) {
    const reply = globalThis.browser
      ? await globalThis.browser.runtime.sendMessage(message)
      : await new Promise((resolve, reject) => {
          api.runtime.sendMessage(message, (answer) => {
            if (api.runtime.lastError) reject(new Error(api.runtime.lastError.message));
            else resolve(answer);
          });
        });
    if (!reply || reply.error) throw new Error(reply?.error ?? "The extension didn't answer. Reload the page and try again.");
    return reply.data;
  }

  // ---------- filling ----------

  async function fill(id, report) {
    const entry = await ask({ type: "joblog", id });
    const fields = entry.sections.flatMap((section) => section.fields);
    // Deltid first: Antal timer pr. uge only shows after it.
    fields.sort((a, b) => (a.name === "jobType" ? -1 : b.name === "jobType" ? 1 : 0));
    for (const field of fields) {
      try {
        const done = await fillField(field);
        if (done) report(`${field.label}: ${done.length > 60 ? `${done.slice(0, 60)}…` : done}`, true);
      } catch (error) {
        report(`${field.label}: ${error.message}`, false);
      }
    }
    for (const file of entry.files) {
      if (!file.ready) {
        report(`${file.label}: ${file.missing}`, false);
        continue;
      }
      try {
        const pdf = await ask({ type: "pdf", id, kind: file.kind });
        attach(UPLOADS[file.kind], pdf);
        report(`${file.label}: ${pdf.name}`, true);
      } catch (error) {
        report(`${file.label}: ${error.message}`, false);
      }
    }
  }

  // Fills one field; returns what it did, or "" when there was nothing to fill.
  async function fillField(field) {
    const { name, type, value } = field;
    if (type === "radio") {
      const radio = form().querySelector(`input[type="radio"][name="${name}"][value="${CSS.escape(value)}"]`);
      if (!radio) throw new Error(`no "${field.options?.[value] ?? value}" option on the form`);
      if (!radio.checked) radio.click();
      return field.options?.[value] ?? value;
    }
    if (type === "checkbox") {
      const box = element(name);
      if (box.checked !== Boolean(value)) box.click();
      return value ? "ticked" : "";
    }
    if (value === "") return "";
    if (TYPEAHEADS.includes(name)) return pick(element(name), value);
    const node = element(name);
    setValue(node, value);
    if (name === "submissionDeadline") {
      // The date picker reads the typed date; Escape closes its calendar.
      node.dispatchEvent(new KeyboardEvent("keydown", { key: "Escape", bubbles: true }));
      node.blur();
    }
    if (node.value !== String(value)) throw new Error(`Jobnet didn't take "${value}"`);
    return value;
  }

  function element(name) {
    const node = form().querySelector(`[name="${name}"]`);
    if (!node) throw new Error("not on the form (hidden, or Jobnet changed it)");
    return node;
  }

  function setValue(node, value) {
    const prototype = Object.getPrototypeOf(node);
    const setter = Object.getOwnPropertyDescriptor(prototype, "value")?.set;
    node.focus();
    if (setter) setter.call(node, value);
    else node.value = value;
    node.dispatchEvent(new Event("input", { bubbles: true }));
    node.dispatchEvent(new Event("change", { bubbles: true }));
  }

  // Jobnet only keeps a value picked from its suggestions (its occupation
  // list, the CVR register, postcodes): typed text is cleared when the field
  // loses focus. So the value is typed, then shorter searches made from it
  // ("Backend-udvikler" finds nothing, "Backend" finds "Softwareudvikler,
  // backend"), and the suggestion sharing the most words with it is picked.
  async function pick(input, value) {
    const listbox = () => document.getElementById(input.getAttribute("aria-controls") ?? "");
    let best = null;
    for (const query of searches(input.name, value)) {
      setValue(input, query);
      const options = await until(() => {
        const found = [...(listbox()?.querySelectorAll('[role="option"]') ?? [])];
        return found.length ? found : null;
      }, 2500);
      for (const option of options ?? []) {
        const score = likeness(value, option.textContent);
        if (score > (best?.score ?? 0)) best = { option, score };
      }
      if (best?.score >= 1) break;
    }
    // Fewer than half the words in common is someone else's job or company.
    if (!best || best.score < 0.5) {
      setValue(input, "");
      throw new Error(`none of Jobnet's suggestions fits "${value}": pick one yourself`);
    }
    // The option may have gone with a later search: search for it again.
    if (!best.option.isConnected) {
      setValue(input, best.option.textContent.trim());
      const again = await until(() => [...(listbox()?.querySelectorAll('[role="option"]') ?? [])].find((o) => o.textContent.trim() === best.option.textContent.trim()), 2500);
      if (!again) throw new Error(`Jobnet's suggestion "${best.option.textContent.trim()}" went away: pick one yourself`);
      best.option = again;
    }
    const picked = best.option.textContent.trim();
    for (const type of ["mousedown", "mouseup", "click"]) best.option.dispatchEvent(new MouseEvent(type, { bubbles: true, cancelable: true }));
    return picked.toLowerCase() === value.toLowerCase() ? picked : `${picked} (closest to "${value}")`;
  }

  // The searches to try for a value, the whole value first.
  function searches(name, value) {
    const list = [value];
    if (name === "postalCodeAndCity") list.push(value.slice(0, 4));
    if (name === "name") list.push(value.replace(/\s+(?:a\/s|aps|i\/s|p\/s|k\/s|ivs|amba|a\.m\.b\.a\.)\.?$/i, ""), words(value).slice(0, 2).join(" "));
    if (name === "occupation") list.push(...[...words(value)].sort((a, b) => b.length - a.length));
    return [...new Set(list.filter((query) => query.length >= 2))];
  }

  function words(text) {
    return text.toLowerCase().split(/[^\p{L}\d]+/u).filter((word) => word.length >= 3);
  }

  // How alike a suggestion is to the value: 1 when it is the value, else the
  // share of the value's words found in it ("udvikler" in "softwareudvikler").
  function likeness(value, suggestion) {
    const text = suggestion.trim().toLowerCase();
    if (text === value.trim().toLowerCase()) return 1;
    const wanted = words(value);
    if (!wanted.length) return 0;
    return (wanted.filter((word) => text.includes(word)).length / wanted.length) * 0.99;
  }

  function until(check, ms) {
    return new Promise((resolve) => {
      const started = Date.now();
      const tick = () => {
        const found = check();
        if (found || Date.now() - started > ms) resolve(found);
        else setTimeout(tick, 100);
      };
      tick();
    });
  }

  function attach(name, pdf) {
    const input = document.querySelector(`input[type="file"][name="${name}"]`);
    if (!input) throw new Error("Jobnet's upload field isn't on the page");
    const bytes = Uint8Array.from(atob(pdf.base64), (c) => c.charCodeAt(0));
    const files = new DataTransfer();
    files.items.add(new File([bytes], pdf.name, { type: "application/pdf" }));
    input.files = files.files;
    input.dispatchEvent(new Event("input", { bubbles: true }));
    input.dispatchEvent(new Event("change", { bubbles: true }));
  }
})();
