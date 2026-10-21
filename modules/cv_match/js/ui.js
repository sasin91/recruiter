// Small UI helpers for the CV match page: confirm dialogs, the success and
// error animation, toasts, counting numbers, list reordering and file drops.
//
// The dialogs follow Trongate MX's modal and its mx-animate-success /
// mx-animate-error icons (the same circle, tick and cross, drawn by animating
// the stroke), on a native <dialog> so focus, Escape and the backdrop work
// without extra code. Every animation is CSS; with prefers-reduced-motion the
// stylesheet turns them off and these helpers skip the waits.

export const reducedMotion = () => window.matchMedia("(prefers-reduced-motion: reduce)").matches;

const SVG = "http://www.w3.org/2000/svg";

// Trongate MX's icon paths (viewBox 0 0 37 37).
const ICONS = {
  success: [["polyline", { class: "mx-tick", points: "11.6,20 15.9,24.2 26.4,13.8" }]],
  error: [
    ["polyline", { class: "mx-tick", points: "11.1,10 25.4,27.2" }],
    ["polyline", { class: "mx-cross", points: "24.1,10 12.4,27.2" }],
  ],
  warning: [
    ["line", { class: "mx-tick", x1: "18.5", y1: "9", x2: "18.5", y2: "21" }],
    ["line", { class: "mx-cross dot", x1: "18.5", y1: "26.5", x2: "18.5", y2: "27" }],
  ],
};
const CIRCLE = "M30.5,6.5L30.5,6.5c6.6,6.6,6.6,17.4,0,24l0,0c-6.6,6.6-17.4,6.6-24,0l0,0c-6.6-6.6-6.6-17.4,0-24l0,0C13.1-0.2,23.9-0.2,30.5,6.5z";

function icon(kind) {
  const svg = document.createElementNS(SVG, "svg");
  svg.setAttribute("viewBox", "0 0 37 37");
  svg.setAttribute("class", `mx-icon ${kind}`);
  svg.setAttribute("aria-hidden", "true");
  const circle = document.createElementNS(SVG, "path");
  circle.setAttribute("class", "mx-circ");
  circle.setAttribute("d", CIRCLE);
  svg.append(circle);
  for (const [tag, attrs] of ICONS[kind]) {
    const node = document.createElementNS(SVG, tag);
    for (const [name, value] of Object.entries(attrs)) node.setAttribute(name, value);
    svg.append(node);
  }
  // Drawn on the next frame, so the stroke transition runs.
  requestAnimationFrame(() => requestAnimationFrame(() => svg.classList.add("drawn")));
  return svg;
}

function el(tag, props = {}, children = []) {
  const node = Object.assign(document.createElement(tag), props);
  node.append(...children);
  return node;
}

// Closes a dialog after its closing animation.
function closeAnimated(dialog, value) {
  if (!dialog.open || dialog.classList.contains("closing")) return;
  dialog.returnValue = value;
  if (reducedMotion()) {
    dialog.close(value);
    return;
  }
  dialog.classList.add("closing");
  dialog.addEventListener(
    "animationend",
    () => {
      dialog.classList.remove("closing");
      dialog.close(value);
    },
    { once: true },
  );
}

function openDialog(className, children) {
  const dialog = el("dialog", { className: `mx-dialog ${className}` }, children);
  dialog.addEventListener("cancel", (e) => {
    e.preventDefault();
    closeAnimated(dialog, "cancel");
  });
  // A click on the backdrop (the dialog element itself, outside its box) cancels.
  dialog.addEventListener("click", (e) => {
    if (e.target === dialog) closeAnimated(dialog, "cancel");
  });
  dialog.addEventListener("close", () => dialog.remove());
  document.body.append(dialog);
  dialog.showModal();
  return dialog;
}

/**
 * Asks before something destructive or paid. Resolves true when confirmed.
 * tone "danger" colours the confirm button red.
 */
export function confirmDialog({ title, text = "", confirm = "Continue", cancel = "Cancel", tone = "primary" }) {
  const yes = el("button", { type: "button", className: tone }, [confirm]);
  const no = el("button", { type: "button" }, [cancel]);
  const dialog = openDialog("confirm", [
    icon(tone === "danger" ? "error" : "warning"),
    el("h2", { id: "mx-dialog-title" }, [title]),
    text ? el("p", {}, [text]) : "",
    el("div", { className: "mx-dialog-buttons" }, [no, yes]),
  ]);
  dialog.setAttribute("aria-labelledby", "mx-dialog-title");
  no.addEventListener("click", () => closeAnimated(dialog, "cancel"));
  yes.addEventListener("click", () => closeAnimated(dialog, "confirm"));
  // The safe choice has focus, so Enter doesn't spend or delete by accident.
  no.focus();
  return new Promise((resolve) => dialog.addEventListener("close", () => resolve(dialog.returnValue === "confirm"), { once: true }));
}

/**
 * The big tick (or cross) of mx-animate-success / mx-animate-error with a
 * line of text. Closes itself after MX's 1.3 seconds, or on a click.
 */
export function flash(kind, title, text = "") {
  const dialog = openDialog("flash", [icon(kind), el("h2", {}, [title]), text ? el("p", {}, [text]) : ""]);
  dialog.addEventListener("click", () => closeAnimated(dialog, "ok"));
  const timer = setTimeout(() => closeAnimated(dialog, "ok"), reducedMotion() ? 900 : 1300);
  return new Promise((resolve) =>
    dialog.addEventListener(
      "close",
      () => {
        clearTimeout(timer);
        resolve();
      },
      { once: true },
    ),
  );
}

/** A short note in the corner that goes away by itself. tone: "", "success" or "error". */
export function toast(text, tone = "") {
  let stack = document.getElementById("toasts");
  if (!stack) {
    stack = el("div", { id: "toasts", className: "toasts" });
    stack.setAttribute("role", "status");
    stack.setAttribute("aria-live", "polite");
    document.body.append(stack);
  }
  const note = el("div", { className: `toast ${tone}` }, [text]);
  stack.append(note);
  setTimeout(() => {
    if (reducedMotion()) return note.remove();
    note.classList.add("leaving");
    note.addEventListener("animationend", () => note.remove(), { once: true });
  }, 3200);
}

/** Counts a number up from 0 to `to`. */
export function countUp(node, to, duration = 900) {
  if (reducedMotion()) {
    node.textContent = to;
    return;
  }
  const start = performance.now();
  const step = (now) => {
    const t = Math.min(1, (now - start) / duration);
    // Ease out cubic: fast at first, settling on the number.
    node.textContent = Math.round(to * (1 - (1 - t) ** 3));
    if (t < 1) requestAnimationFrame(step);
  };
  requestAnimationFrame(step);
}

/**
 * Changes a list with `change` and slides the children that stay from their
 * old place to the new one (FLIP), so a row that moves up the ranking is seen
 * moving.
 */
export function flip(list, change) {
  const before = new Map([...list.children].map((child) => [child, child.getBoundingClientRect().top]));
  change();
  if (reducedMotion()) return;
  for (const child of list.children) {
    const top = before.get(child);
    if (top === undefined) continue;
    const moved = top - child.getBoundingClientRect().top;
    if (!moved) continue;
    child.animate([{ transform: `translateY(${moved}px)` }, { transform: "none" }], { duration: 350, easing: "cubic-bezier(.2,.8,.2,1)" });
  }
}

/** Lets a file be dropped on `zone`; `onFile` gets the first file dropped. */
export function dropZone(zone, onFile) {
  let depth = 0;
  zone.addEventListener("dragenter", (e) => {
    if (!e.dataTransfer?.types.includes("Files")) return;
    e.preventDefault();
    depth++;
    zone.classList.add("drag-over");
  });
  zone.addEventListener("dragover", (e) => {
    if (e.dataTransfer?.types.includes("Files")) e.preventDefault();
  });
  zone.addEventListener("dragleave", () => {
    if (--depth <= 0) {
      depth = 0;
      zone.classList.remove("drag-over");
    }
  });
  zone.addEventListener("drop", (e) => {
    const file = e.dataTransfer?.files?.[0];
    if (!file) return;
    e.preventDefault();
    depth = 0;
    zone.classList.remove("drag-over");
    onFile(file);
  });
}

/** Fades the nodes in one after another. */
export function reveal(nodes) {
  [...nodes].forEach((node, i) => {
    node.classList.remove("reveal");
    node.style.setProperty("--i", Math.min(i, 12));
    // Restarted when the same node is revealed again.
    void node.offsetWidth;
    node.classList.add("reveal");
  });
}
