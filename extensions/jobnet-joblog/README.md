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

From the browser stores: the links are on https://recruiter.trongate.dev/joblog_extension
(modules/joblog_extension/Store_links.php), and the candidate's pages show a
banner with the one for your browser until the extension is installed.

From this folder (development, or before a store has it):

- **Edge**: `edge://extensions`, turn on Developer mode, "Load unpacked", pick
  this folder.
- **Chrome**: `chrome://extensions`, turn on Developer mode, "Load unpacked",
  pick this folder.
- **Firefox** (140 or later): `about:debugging#/runtime/this-firefox`, "Load
  Temporary Add-on…", pick `manifest.json` (it stays until Firefox restarts).

## Use

Open Jobnet, Joblog, "Opret joblog". A "Fill from Recruiter" box shows in the
bottom right corner: pick the saved match and press "Fill in the form". The box
lists what it filled and what it couldn't.

The matches you have applied for but not entered on Jobnet yet (status Søgt or
Samtale in the match's joblog panel, "entered on Jobnet" not ticked: the list
"Applied, not on Jobnet yet" on Recruiter) come first in the box, with how many
there are. After filling one in and pressing Gem, "Saved it on Jobnet? Mark it
in Recruiter" ticks "entered on Jobnet" for it, so it leaves that list.

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
- `recruiter.js` runs on Recruiter's own pages and only sets
  `data-jobnet-joblog="<version>"` on the page, so its "add the extension"
  banner stays hidden.
- `background.js` fetches from Recruiter (a content script can't fetch from
  another site): the saved matches (`cv_match/history`), the joblog entry and
  the PDFs (`cv_match/pdf`), with your Recruiter cookie, and marks a match as
  entered on Jobnet (`cv_match/joblog_status`).

To point it at another copy of Recruiter (a dev machine), set `base` in the
extension's storage, e.g. from its service worker console:
`chrome.storage.local.set({ base: "http://recruiter.test/" })`, and add that
address to `host_permissions`.

## Publishing

`make extension` (in the repository root) builds `build/jobnet-joblog-<version>.zip`,
the file each store takes: the manifest, the scripts and `icons/`. Bump
`version` in manifest.json for every upload. `store/` holds what the stores
ask for besides the zip: the 440x280 promo tile (Chrome), the 300x300 logo
(Edge) and `listing.md`, the texts for the store forms. The privacy policy is
the "Privacy policy" part of /joblog_extension (modules/joblog_extension/views/index.php);
change it with the manifest. When a listing is live, put its link in
modules/joblog_extension/Store_links.php.
