// The "add the extension" banner (views/banner.php): shown in a desktop
// browser that has a store link, unless the extension is installed (its
// recruiter.js marks the page before this runs) or the banner was closed in
// this browser before.

const banner = document.getElementById("joblog-extension-banner");
const DISMISSED = "joblogExtensionBannerClosed";
const NAMES = { chrome: "Chrome", edge: "Edge", firefox: "Firefox" };

if (banner && !document.documentElement.dataset.jobnetJoblog && !closed()) {
  const browser = which();
  const link = browser && banner.dataset[browser];
  if (link) {
    const add = banner.querySelector(".add");
    add.href = link;
    add.textContent = `Add to ${NAMES[browser]}`;
    banner.querySelector(".close").addEventListener("click", () => {
      banner.hidden = true;
      try {
        localStorage.setItem(DISMISSED, "1");
      } catch {
        // Storage blocked: it comes back on the next page, nothing worse.
      }
    });
    banner.hidden = false;
  }
}

function closed() {
  try {
    return localStorage.getItem(DISMISSED) === "1";
  } catch {
    return false;
  }
}

// Which store this browser installs from, or null (phones, tablets,
// Safari): Edge from its own, other Chromium browsers (Brave, Opera,
// Vivaldi) from Chrome's.
function which() {
  const agent = navigator.userAgent;
  const brands = navigator.userAgentData?.brands?.map((brand) => brand.brand) ?? [];
  if (navigator.userAgentData?.mobile || /Mobi|Android|iPhone|iPad/.test(agent)) return null;
  if (/Firefox\//.test(agent)) return "firefox";
  if (brands.includes("Microsoft Edge") || /Edg\//.test(agent)) return "edge";
  if (brands.includes("Chromium") || /Chrome\//.test(agent)) return "chrome";
  return null;
}
