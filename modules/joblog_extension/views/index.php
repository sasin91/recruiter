<?php
/**
 * /joblog_extension: what the Jobnet joblog extension does, where to get it
 * ($stores, each {name, url}; $some_unlisted when a browser has no store
 * link yet) and its privacy policy. $signed_in
 * picks the top bar. The stores link here as the extension's home page and
 * privacy policy, so what it says about data must match
 * extensions/jobnet-joblog/manifest.json (tests/the_privacy_policy_names_every_permission.phpt).
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="A browser extension that fills in Jobnet's Opret joblog form from a match you saved in Recruiter.">
    <title>Joblog filler · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav>
        <?php if ($signed_in): ?>
            <a href="cv_match">CV match</a>
            <a href="job_scan">Job scan</a>
            <a href="account">Account</a>
        <?php else: ?>
            <a href="sign-in">Sign in</a>
            <a class="button" href="register">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main class="prose">
    <h1>Joblog filler</h1>
    <p class="lead">A browser extension that fills in Jobnet's "Opret joblog" form from a job match you saved in Recruiter: job title, company, address, deadline, contact, the day you applied, your notes, and the application, CV and job ad as PDFs. The jobs you have applied for but not logged on Jobnet yet come first. It never presses Gem: you check the form and save it yourself, and Recruiter then knows that job is on Jobnet.</p>
    <p class="muted">Made by Recruiter. It is not made by, or affiliated with, Jobnet or Styrelsen for Arbejdsmarked og Rekruttering.</p>

    <h2 id="install">Get it</h2>
    <?php if ($stores): ?>
        <ul>
            <?php foreach ($stores as $store): ?>
                <li><a href="<?= out($store['url']) ?>" rel="noopener"><?= out($store['name']) ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php if ($some_unlisted): ?>
        <p><?= $stores ? 'For the other browsers' : 'Until it is in the browser stores' ?>, load it from <a href="https://github.com/sasin91/recruiter/tree/master/extensions/jobnet-joblog" rel="noopener">its folder on GitHub</a>: in Chrome or Edge, open the extensions page, turn on Developer mode and choose "Load unpacked".</p>
    <?php endif; ?>

    <h2 id="use">How to use it</h2>
    <ol>
        <li>Sign in to Recruiter in the same browser and save a match on the <a href="cv_match">CV match</a> page.</li>
        <li>On Jobnet, open Joblog and "Opret joblog". A "Fill from Recruiter" box shows in the corner.</li>
        <li>Pick the match, press "Fill in the form", check what it filled, then press Gem.</li>
    </ol>
    <p>Jobnet only keeps a company or postcode picked from its own suggestions, so the extension picks the closest one and says when it differs from your match. A company Jobnet doesn't know keeps its name as typed.</p>

    <h2 id="privacy">Privacy policy</h2>
    <p>The extension collects nothing and sends nothing to Recruiter or anyone else beyond what is listed here.</p>
    <ul>
        <li><strong>What it reads:</strong> your own saved matches on Recruiter (recruiter.trongate.dev): the joblog fields and the application, CV and job ad PDFs. It reads them with your Recruiter sign-in, only when you open the box on Jobnet or press "Fill in the form".</li>
        <li><strong>Where it goes:</strong> into the Opret joblog form in your own Jobnet tab (jobnet.dk). Jobnet receives it only when you press Gem.</li>
        <li><strong>What it tells Recruiter:</strong> when you save a form it filled in with Gem, which of your matches is now in your Jobnet joblog and the status you gave it (Ikke søgt, Søgt or Samtale), as ticking "entered on Jobnet" on the match does. Nothing from Jobnet's page is sent.</li>
        <li><strong>The company register:</strong> when a job post has no postcode and town, the company's name is looked up on cvrapi.dk for its registered address. Only the company name is sent.</li>
        <li><strong>What it stores:</strong> nothing about you. Its browser storage (the storage permission) only holds which Recruiter address to use.</li>
        <li><strong>On Recruiter's pages</strong> it only marks the page as having the extension, so Recruiter stops suggesting it.</li>
        <li>No analytics, no ads, and your data is never sold or shared. The use of information received from Recruiter and Jobnet adheres to the Chrome Web Store User Data Policy, including the Limited Use requirements.</li>
    </ul>
    <p>Your matches are stored by Recruiter itself, under your account, as on the CV match page. The extension keeps no copy of them.</p>

    <h2 id="support">Questions and problems</h2>
    <p>Report a problem or ask a question on <a href="https://github.com/sasin91/recruiter/issues" rel="noopener">Recruiter's GitHub issues</a>.</p>
</main>
</body>
</html>
