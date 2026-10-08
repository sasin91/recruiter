// Company sign-up: once the CVR number is 8 digits, look the company up in
// the CVR register (company/cvr_lookup, from cvrapi.dk) and fill in its name.
// A name the person typed themselves is never overwritten. Without this
// script the form works as before: type the name.
const cvr = document.getElementById("cvr");
const name = document.getElementById("company_name");
const find = document.getElementById("cvr-find");
const result = document.getElementById("cvr-result");
let filled = ""; // the name this script last filled in
let asked = "";

find.hidden = false;
find.addEventListener("click", () => lookUp(true));
cvr.addEventListener("input", () => lookUp(false));
cvr.addEventListener("keydown", (event) => {
  if (event.key === "Enter") {
    event.preventDefault();
    lookUp(true);
  }
});
if (cvr.value) lookUp(false);

async function lookUp(again) {
  const digits = cvr.value.replace(/\s+/g, "").replace(/^DK/i, "");
  if (!/^\d{8}$/.test(digits)) {
    if (again) show("A CVR number is 8 digits.", "problem");
    return;
  }
  if (digits === asked && !again) return;
  asked = digits;
  show("Looking it up…", "");
  find.disabled = true;
  try {
    const response = await fetch(`company/cvr_lookup?cvr=${encodeURIComponent(digits)}`, { headers: { Accept: "application/json" } });
    const company = await response.json();
    if (digits !== asked) return; // typed on meanwhile
    if (!response.ok || company.error) {
      show(company.error ?? "The CVR lookup isn't available right now. Fill in the company's name yourself.", "problem");
      return;
    }
    if (name.value.trim() === "" || name.value === filled) {
      name.value = filled = company.name;
    }
    const where = [company.address, [company.postal_code, company.city].filter(Boolean).join(" ")].filter(Boolean).join(", ");
    const kind = company.company_type ? ` · ${company.company_type}` : "";
    show(`${company.name}${where ? `, ${where}` : ""}${kind}${company.ended ? ". The register says this company has closed." : ""}`, company.ended ? "problem" : "found");
  } catch {
    show("The CVR lookup isn't available right now. Fill in the company's name yourself.", "problem");
  } finally {
    find.disabled = false;
  }
}

function show(text, kind) {
  result.textContent = text;
  result.className = `small ${kind || "muted"}`;
}
