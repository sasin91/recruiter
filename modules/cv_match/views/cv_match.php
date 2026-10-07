<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= $base_url ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CV match · Recruiter</title>
    <link rel="stylesheet" href="cv_match_module/css/cv_match.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $base_url ?>">Recruiter</a>
    <nav>
        <?php if ($signed_in): ?>
            <a href="account">Account</a>
            <a href="login/logout">Sign out</a>
        <?php else: ?>
            <a href="sign-in">Sign in</a>
            <a href="register">Create account</a>
        <?php endif; ?>
    </nav>
</header>
<main>
    <h1>CV match</h1>
    <?php if (!$signed_in): ?>
        <p class="notice">You're using the free keyword match. <a href="sign-in">Sign in</a> or <a href="register">create an account</a> and add your own OpenAI or Anthropic API key for the AI match: it reads requirements written as sentences, judges related experience, fetches posts from a link and writes your application.</p>
    <?php elseif (!$ai_ready): ?>
        <p class="notice">You're using the free keyword match. Add your own OpenAI or Anthropic API key on your <a href="account">account page</a> to turn on the AI match: it reads requirements written as sentences, judges related experience and writes your application.</p>
    <?php endif; ?>

    <section class="panel" id="cv-panel">
        <h2>Your CV</h2>
        <p id="cv-status" class="muted">No CV yet.</p>
        <div id="cv-profile"></div>
        <details id="cv-input">
            <summary>Upload or paste a CV</summary>
            <input type="file" id="cv-file" accept=".pdf,.txt,.md,.html,.htm">
            <textarea id="cv-text" rows="8" placeholder="…or paste the CV text here"></textarea>
            <?php if ($ai_ready): ?>
                <button type="button" id="cv-read">Read CV</button>
            <?php endif; ?>
        </details>
    </section>

    <section class="panel">
        <h2>Job post</h2>
        <?php if ($signed_in): ?>
            <div class="url-row">
                <input type="url" id="job-url" placeholder="Paste a link to the job post" autocomplete="off">
                <button type="button" id="job-fetch">Fetch</button>
            </div>
        <?php endif; ?>
        <input type="file" id="job-file" accept=".pdf,.txt,.md,.html,.htm">
        <textarea id="job-text" rows="12" autocomplete="off" placeholder="…or paste the job post text here, or upload it above"></textarea>
        <div id="more-jobs"></div>
        <div class="actions">
            <button type="button" id="match" class="primary">Match</button>
            <button type="button" id="add-job">Add another job post</button>
        </div>
        <p id="progress" class="muted" aria-live="polite"></p>
    </section>

    <section id="batch-result" hidden>
        <h2>Jobs ranked by match</h2>
        <p id="batch-note" class="muted"></p>
        <ol id="batch-list"></ol>
    </section>

    <section id="result" hidden>
        <div class="summary">
            <div class="score"><span id="score-value"></span><small>%</small></div>
            <div>
                <div id="score-tag" class="tag"></div>
                <div id="job-heading"></div>
                <div id="score-parts" class="muted"></div>
            </div>
        </div>
        <div id="groups"></div>
        <div id="soft-skills"></div>

        <section class="panel" id="application-panel">
            <h3>Application and résumé</h3>
            <div id="documents"></div>
        </section>

        <div id="laya-block">
            <h3>For Laya</h3>
            <p id="laya-answer" hidden></p>
            <p class="muted">The job post and CV in English with a summary of the requirement verdicts: the input Laya's English checkpoint ranked well in the bake-off.</p>
            <textarea id="laya" rows="6" readonly></textarea>
        </div>
    </section>

    <section class="panel" id="upgrade-panel" hidden>
        <h3>Want the full picture?</h3>
        <p>This was the free keyword match: it only finds requirements the post names as a skill, tool or title, and counts anything your CV doesn't name word for word as missing. The AI match reads the whole post, judges related experience, saves the match and writes a job application from your CV.</p>
        <?php if ($signed_in): ?>
            <p><a class="button-link primary" href="account">Add your API key</a></p>
        <?php else: ?>
            <p><a class="button-link primary" href="register">Create an account</a> <a href="sign-in">or sign in</a></p>
        <?php endif; ?>
    </section>

    <?php if ($signed_in): ?>
        <section class="panel" id="history-panel">
            <h2>Saved matches</h2>
            <p id="history-empty" class="muted">No saved matches yet.</p>
            <ul id="history"></ul>
        </section>
    <?php endif; ?>
</main>
<script type="module">
    import { start } from "./cv_match_module/js/app.js";
    start(document.baseURI, { signedIn: <?= json_encode($signed_in) ?>, aiReady: <?= json_encode($ai_ready) ?>, maxJobs: <?= $max_jobs ?> });
</script>
</body>
</html>
