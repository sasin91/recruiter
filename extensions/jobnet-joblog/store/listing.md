# Store listing texts

The same texts go to the Chrome Web Store, Edge Add-ons, Firefox Add-ons
(AMO) and the App Store (Safari). Upload `build/jobnet-joblog-<version>.zip` from `make extension`.

## Name

Joblog filler for Jobnet, by Recruiter

## Summary (132 characters at most)

Fills in Jobnet's "Opret joblog" form from a match you saved in Recruiter, PDFs included. Not made by Jobnet.

## Description

Joblog filler for Jobnet fills in Jobnet's "Opret joblog" form from a job
match you saved in Recruiter (https://recruiter.trongate.dev), so you don't
have to copy each field by hand.

It fills in the job title, company, address, postcode and town, deadline,
full or part time, contact person, your notes and status, and attaches your
application, CV and the job ad as PDFs. It never presses Gem: you check the
form and save it yourself.

Jobnet only keeps a job title, company or postcode picked from its own
suggestions, so the extension picks the closest suggestion and tells you when
it differs from your match.

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
  and their PDFs with the user's Recruiter sign-in, and marks Recruiter's
  pages as having the extension so they stop suggesting it.
- Host permission cvrapi.dk: Looks up the company's registered postcode and
  town in the Danish company register when the job post has none. Only the
  company name is sent.
- Content script on jobnet.dk: Shows the fill box on the Opret joblog form and
  fills in the form in the user's tab. It never submits it.

**Remote code:** No. All code is in the package.

**Data usage:** Tick "Personally identifiable information": Chrome counts data
an extension handles, and it reads the user's CV and application from
Recruiter and puts them into the Jobnet form in the user's own tab. Certify: not sold, not
used for anything unrelated to the single purpose, not used for
creditworthiness.

## Firefox

`data_collection_permissions` is `none` in the manifest: the extension
transmits nothing to us or to third parties beyond putting the user's own
data into the Jobnet form in their tab, and the company name to cvrapi.dk.
No source code upload is needed: nothing is built or minified.

## Safari (App Store Connect)

Needs the Apple Developer Program. No Mac or Xcode: Apple packages the zip
([Apple's guide](https://developer.apple.com/documentation/safariservices/packaging-and-distributing-safari-web-extensions-with-app-store-connect)).

1. App Store Connect, Apps, +, New App: platform macOS, name "Joblog filler
   for Jobnet", bundle ID `dev.trongate.recruiter.joblog`, SKU `joblog-filler`.
2. The app's Xcode Cloud tab, Safari Web Extension Packager, Upload: the zip
   from `make extension`. Check the build's warnings on the Builds page.
3. TestFlight: install it on your Mac and do one fill on Jobnet. In Safari,
   allow the extension on recruiter.trongate.dev, jobnet.dk and cvrapi.dk
   when it asks.
4. App information: category Productivity; age rating none of the content
   questions; privacy policy and support URL as above.
5. App Privacy: "Data Not Collected" (nothing leaves the Mac for us or
   anyone else; see the Firefox note below).
6. Screenshots: at least one at 1280x800 (or 1440x900, 2560x1600, 2880x1800).
7. Add the build to the version, submit for review. The App Store link
   (apps.apple.com/...) goes in Store_links::SAFARI.

## Notes for the reviewers

Jobnet needs a MitID login, so you can't open its joblog form. A screen
recording of a fill is here: <video link>. A Recruiter test account with one
saved match: <username> / <password> (sign in at
https://recruiter.trongate.dev/sign-in, then open https://recruiter.trongate.dev/cv_match).
