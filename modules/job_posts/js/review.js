// The review page: "Add a requirement" copies the last row with empty
// fields, and a row marked Remove is greyed out until the form is saved.
const table = document.getElementById("requirements");
const body = table.tBodies[0];

document.getElementById("add-requirement").addEventListener("click", () => {
  const last = body.rows[body.rows.length - 1];
  const row = last.cloneNode(true);
  const index = body.rows.length;
  row.classList.remove("removed");
  for (const input of row.querySelectorAll("input, select")) {
    input.name = input.name.replace(/requirements\[\d+\]/, `requirements[${index}]`);
    if (input.type === "checkbox") input.checked = input.name.endsWith("[required]");
    else if (input.tagName === "SELECT") input.value = "skill";
    else input.value = "";
  }
  body.append(row);
  row.querySelector("input[type=text]").focus();
});

body.addEventListener("change", (event) => {
  if (event.target.name?.endsWith("[remove]")) {
    event.target.closest("tr").classList.toggle("removed", event.target.checked);
  }
});
