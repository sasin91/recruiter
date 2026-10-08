// Company sign-up: once the CVR number is 8 digits, look the company up in
// the CVR register at cvrapi.dk and fill in its name. The browser asks
// cvrapi.dk itself, so lookups count against each visitor's own address and
// never against the server's quota. A name the person typed themselves is
// never overwritten. Without this script, or when the lookup fails, the form
// works as before: type the name.
const CVRAPI = "https://cvrapi.dk/api";
const UNAVAILABLE = "The CVR lookup isn't available right now. Fill in the company's name yourself.";
// What cvrapi.dk's error codes mean for someone signing up.
const ERRORS = {
  NOT_FOUND: "No company has that CVR number.",
  INVALID_VAT: "That isn't a CVR number.",
  QUOTA_EXCEEDED: "The CVR lookup is busy right now. Fill in the company's name yourself.",
};
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
    const response = await fetch(`${CVRAPI}?${new URLSearchParams({ search: digits, country: "dk" })}`, { headers: { Accept: "application/json" } });
    const answer = await response.json();
    if (digits !== asked) return; // typed on meanwhile
    if (answer.error || !answer.name) {
      show(ERRORS[answer.error] ?? UNAVAILABLE, "problem");
      return;
    }
    const company = {
      name: String(answer.name).trim(),
      address: answer.address ?? "",
      postal_code: answer.zipcode ?? "",
      city: answer.city ?? "",
      company_type: answer.companydesc ?? "",
      ended: Boolean(answer.enddate),
    };
    if (name.value.trim() === "" || name.value === filled) {
      name.value = filled = company.name;
    }
    const where = [company.address, [company.postal_code, company.city].filter(Boolean).join(" ")].filter(Boolean).join(", ");
    const kind = company.company_type ? ` · ${company.company_type}` : "";
    show(`${company.name}${where ? `, ${where}` : ""}${kind}${company.ended ? ". The register says this company has closed." : ""}`, company.ended ? "problem" : "found");
  } catch {
    show(UNAVAILABLE, "problem");
  } finally {
    find.disabled = false;
  }
}

function show(text, kind) {
  result.textContent = text;
  result.className = `small ${kind || "muted"}`;
}
