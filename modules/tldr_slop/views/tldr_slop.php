<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= $base_url ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TL;DR slop · Recruiter</title>
    <link rel="stylesheet" href="cv_match_module/css/cv_match.css">
    <link rel="stylesheet" href="tldr_slop_module/css/tldr_slop.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= $base_url ?>">Recruiter</a>
</header>
<main>
    <div class="hero">
        <h1>TL;DR slop</h1>
        <p class="muted">Paste a link to an article. You get the gist in three sentences and whether it's slop: generic, padded text with little real content.</p>
    </div>

    <form class="panel" id="check-form">
        <div class="url-row">
            <input type="url" id="article-url" placeholder="https://dev.to/…" autocomplete="off">
            <button type="submit" id="check"><span class="label">Check</span></button>
        </div>
        <details id="paste">
            <summary>Or paste the text</summary>
            <textarea id="article-text" rows="8" placeholder="Paste the article here when the site won't let us read it"></textarea>
        </details>
        <p class="error" id="error" role="alert" hidden></p>
    </form>

    <section class="panel" id="result" hidden aria-live="polite">
        <div class="verdict" id="verdict"></div>
        <h2 id="title"></h2>
        <h3>TL;DR</h3>
        <ol id="tldr"></ol>
        <h3>What Laya was told</h3>
        <p class="muted" id="summary"></p>
        <details>
            <summary>Every sentence <span class="muted" id="legend"></span></summary>
            <ol class="sentences" id="sentences"></ol>
        </details>
    </section>
</main>
<script type="module" src="tldr_slop_module/js/app.js"></script>
</body>
</html>
