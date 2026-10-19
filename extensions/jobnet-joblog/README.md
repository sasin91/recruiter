# Recruiter: Jobnet joblog

A browser extension that fills in Jobnet's "Opret joblog" form from a saved
match in Recruiter's CV checker: the same values the match's "Jobnet joblog"
panel shows (`cv_match/joblog/{id}`, modules/cv_match/Joblog.php), and the
application, CV and job post as PDFs in Jobnet's upload fields. It never
presses Gem: check the form and save it yourself.

One folder for Chrome, Edge and Firefox (Manifest V3). No build step.

## Install

Sign in to Recruiter (recruiter.trongate.dev) in the same browser first: the
extension uses that sign-in.

- **Edge**: `edge://extensions`, turn on Developer mode, "Load unpacked", pick
  this folder.
- **Chrome**: `chrome://extensions`, turn on Developer mode, "Load unpacked",
  pick this folder.
- **Firefox**: `about:debugging#/runtime/this-firefox`, "Load Temporary
  Add-on…", pick `manifest.json` (it stays until Firefox restarts; to keep it,
  sign it as an unlisted add-on on addons.mozilla.org). Then in
  `about:addons`, the extension's Permissions tab, allow recruiter.trongate.dev:
  Firefox doesn't grant a Manifest V3 extension its sites until you do.

## Use

Open Jobnet, Joblog, "Opret joblog". A "Fill from Recruiter" box shows in the
bottom right corner: pick the saved match and press "Fill in the form". The box
lists what it filled and what it couldn't.

Jobnet only keeps a job title, company or postcode picked from its own
suggestions (its occupation list, the CVR register, postcodes) and clears typed
text. So for those the extension searches with the value, then with parts of it,
and picks the suggestion sharing the most words with it: "Backend-udvikler"
becomes "Softwareudvikler, backend", "Nordisk Fragt A/S" becomes "Nordisk Fragt
ApS". The box says when it picked something other than the value itself, and
leaves the field empty when nothing fits.

## How it works

- `content.js` runs on jobnet.dk. Jobnet's form is React, which ignores values
  set from outside, so each value is set through the element's own prototype
  setter and announced with an input event, as typing would; radio buttons and
  the checkbox are clicked; PDFs are attached to the upload fields with a
  DataTransfer.
- `background.js` fetches from Recruiter (a content script can't fetch from
  another site): the saved matches (`cv_match/history`), the joblog entry and
  the PDFs (`cv_match/pdf`), with your Recruiter cookie.

To point it at another copy of Recruiter (a dev machine), set `base` in the
extension's storage, e.g. from its service worker console:
`chrome.storage.local.set({ base: "http://recruiter.test/" })`, and add that
address to `host_permissions`.
