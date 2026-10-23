// The profile page's CV picker: reads a PDF or text file in the browser
// into the CV box (pdf.js, as on cv_match), so only text is posted.
const PDFJS = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.10.38/";

const picker = document.getElementById("cv_file");
const box = document.getElementById("cv_text");
const name = document.querySelector('#profile-fill input[name="cv_name"]');

picker?.addEventListener("change", async () => {
  const file = picker.files[0];
  if (!file) return;
  name.value = file.name;
  box.value = "Reading " + file.name + "…";
  try {
    box.value = await readFile(file);
  } catch (error) {
    box.value = "";
    box.placeholder = "That file could not be read. Paste the CV's text here instead.";
    console.error(error);
  }
});

async function readFile(file) {
  if (file.type === "application/pdf" || file.name.toLowerCase().endsWith(".pdf")) return readPdf(file);
  return file.text();
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
