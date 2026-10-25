# Store listing texts

The same texts go to the Chrome Web Store, Edge Add-ons and Firefox
Add-ons (AMO). Upload `build/jobnet-joblog-<version>.zip` from `make extension`.

## Name

Joblog autofill, by Recruiter

## Summary (132 characters at most)

Fills in Jobnet's "Opret joblog" form from a match you saved in Recruiter, PDFs included. Not made by Jobnet.

## Description

Joblog autofill fills in Jobnet's "Opret joblog" form from a job
match you saved in Recruiter (https://recruiter.trongate.dev), so you don't
have to copy each field by hand.

It fills in the job title, company, address, postcode and town, deadline,
full or part time, contact person, your status and the day you applied, your
notes, and attaches your application, CV and the job ad as PDFs. It never
presses Gem: you check the form and save it yourself.

The jobs you have applied for but not logged on Jobnet yet are listed first.
When you save a filled form with Gem, the extension tells Recruiter, so the
job leaves that list.

Jobnet only keeps a company or postcode picked from its own suggestions, so
the extension picks the closest suggestion and tells you when it differs from
your match. A company Jobnet doesn't know keeps its name as typed.

How to use it:
1. Sign in to Recruiter in the same browser and save a match on the CV match page.
2. On Jobnet, open Joblog and "Opret joblog". A "Fill from Recruiter" box shows in the corner.
3. Pick the match, press "Fill in the form", check it, then press Gem.

The extension is made by Recruiter. It is not made by, or affiliated with,
Jobnet or Styrelsen for Arbejdsmarked og Rekruttering.

## Category

Chrome: Workflow & Planning (Productivity). Edge: Productivity. Firefox: Other.

## Language

English.

## Website, support and privacy policy

- Website: https://recruiter.trongate.dev/joblog_extension
- Privacy policy: https://recruiter.trongate.dev/joblog_extension#privacy
- Support: https://github.com/sasin91/recruiter/issues

## Chrome: privacy practices

**Single purpose:** Fills in Jobnet's "Opret joblog" form from a job match the
user saved in Recruiter, including the application, CV and job ad PDFs.

**Permission justifications:**

- storage: Remembers which Recruiter address to use.
- Host permission recruiter.trongate.dev: Reads the user's own saved matches
  and their PDFs with the user's Recruiter sign-in; after the user saves a
  filled form on Jobnet, tells Recruiter that match is now in their Jobnet
  joblog; and marks Recruiter's pages as having the extension so they stop
  suggesting it.
- Host permission cvrapi.dk: Looks up the company's registered postcode and
  town in the Danish company register when the job post has none. Only the
  company name is sent.
- Content script on jobnet.dk: Shows the fill box on the Opret joblog form and
  fills in the form in the user's tab. It never submits it; it notices when
  the user saves it with Gem.

**Remote code:** No. All code is in the package.

**Data usage:** Tick "Personally identifiable information": Chrome counts data
an extension handles, and it reads the user's CV and application from
Recruiter and puts them into the Jobnet form in the user's own tab. Certify: not sold, not
used for anything unrelated to the single purpose, not used for
creditworthiness.

## Firefox

`data_collection_permissions` requires `websiteContent`: Mozilla counts any
data handled outside the add-on or the browser, and the extension sends the
company name from the saved match to cvrapi.dk when the post has no postcode.
Nothing goes to us; Firefox asks for this consent at install.
No source code upload is needed: nothing is built or minified.

## Notes for the reviewers

Jobnet needs a MitID login, so you can't open its joblog form. A screen
recording of a fill is here: <video link>. A Recruiter test account with one
saved match: <username> / <password> (sign in at
https://recruiter.trongate.dev/sign-in, then open https://recruiter.trongate.dev/cv_match).
