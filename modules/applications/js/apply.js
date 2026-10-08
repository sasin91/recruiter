// The CV step: a chosen file (PDF, text or HTML) is read in the browser into
// the text box, which is what gets sent. PDFs are read with pdf.js, as on the
// CV checker.
const PDFJS = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/";
const file = document.getElementById("cv-file");
const box = document.getElementById("cv_text");
const status = document.getElementById("cv-status");

file.addEventListener("change", async () => {
  const chosen = file.files[0];
  if (!chosen) return;
  status.textContent = `Reading ${chosen.name}…`;
  try {
    box.value = await readFile(chosen);
    document.getElementById("cv_name").value = chosen.name;
    status.textContent = box.value ? `Read ${chosen.name}. Check the text below, then press Read my CV.` : `${chosen.name} has no text in it. Paste the CV instead.`;
  } catch (error) {
    status.textContent = `Couldn't read ${chosen.name}: ${error.message}. Paste the CV instead.`;
  }
});

async function readFile(chosen) {
  if (chosen.type === "application/pdf" || chosen.name.toLowerCase().endsWith(".pdf")) return readPdf(chosen);
  const text = await chosen.text();
  if (/\.html?$/i.test(chosen.name)) return new DOMParser().parseFromString(text, "text/html").body.innerText;
  return text;
}

async function readPdf(chosen) {
  const pdfjs = await import(`${PDFJS}pdf.min.mjs`);
  pdfjs.GlobalWorkerOptions.workerSrc = `${PDFJS}pdf.worker.min.mjs`;
  const pdf = await pdfjs.getDocument({ data: await chosen.arrayBuffer() }).promise;
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
