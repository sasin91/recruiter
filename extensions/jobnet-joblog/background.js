// Talks to Recruiter for the content script on Jobnet: a content script can't
// fetch from another site, the extension can, with the person's own Recruiter
// sign-in (the host permission sends its cookie along).
//
// Messages: {type: "matches"} gives the saved matches, newest first;
// {type: "joblog", id} the joblog entry of one (cv_match/joblog/{id});
// {type: "pdf", id, kind} one of its PDFs as {name, base64}. Each answers
// {error} when Recruiter says no, signed out included.

const api = globalThis.browser ?? globalThis.chrome;
const DEFAULT_BASE = "https://recruiter.trongate.dev/";

async function base() {
  const { base } = await api.storage.local.get("base");
  return base || DEFAULT_BASE;
}

async function call(path, options = {}) {
  const response = await fetch(new URL(path, await base()), { credentials: "include", ...options });
  if (response.status === 401 || response.status === 403 || response.redirected) {
    throw new Error("Sign in to Recruiter in this browser first.");
  }
  if (!response.ok) {
    const data = await response.json().catch(() => ({}));
    throw new Error(data.error ?? `Recruiter answered ${response.status}.`);
  }
  return response;
}

async function answer(message) {
  if (message.type === "matches") {
    return (await (await call("cv_match/history")).json()).matches;
  }
  if (message.type === "joblog") {
    return (await call(`cv_match/joblog/${Number(message.id)}`)).json();
  }
  if (message.type === "pdf") {
    const response = await call("cv_match/pdf", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ id: Number(message.id), kind: message.kind }),
    });
    if (!response.headers.get("Content-Type")?.startsWith("application/pdf")) {
      throw new Error((await response.json().catch(() => ({}))).error ?? "Recruiter sent no PDF.");
    }
    const name = /filename\*=UTF-8''([^;]+)/.exec(response.headers.get("Content-Disposition") ?? "");
    const bytes = new Uint8Array(await response.arrayBuffer());
    let binary = "";
    for (let i = 0; i < bytes.length; i += 0x8000) binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000));
    return { name: name ? decodeURIComponent(name[1]) : `${message.kind}.pdf`, base64: btoa(binary) };
  }
  throw new Error(`Unknown message ${message.type}.`);
}

// sendResponse rather than a returned promise: Chrome doesn't take one.
api.runtime.onMessage.addListener((message, sender, sendResponse) => {
  answer(message).then(
    (data) => sendResponse({ data }),
    (error) => sendResponse({ error: error.message }),
  );
  return true;
});
