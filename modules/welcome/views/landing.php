<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Paste your CV and a job post and see, requirement by requirement, how well you match. Free to try.">
    <title>Recruiter · How well does your CV match the job?</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav>
        <?php if ($signed_in): ?>
            <a href="cv_match">CV match</a>
            <a href="account">Account</a>
            <a href="login/logout">Sign out</a>
        <?php else: ?>
            <a href="sign-in">Sign in</a>
            <a class="button" href="register">Create account</a>
        <?php endif; ?>
    </nav>
</header>

<main>
    <section class="hero">
        <h1>How well does your CV match the job?</h1>
        <p class="lead">Paste your CV and a job post. Recruiter lists the requirements in the post and shows which ones your CV covers, which it partly covers and which are missing, with a match score out of 100.</p>
        <p class="cta">
            <a class="button primary big" href="cv_match">Match my CV, free</a>
            <?php if (!$signed_in): ?>
                <span class="muted">No account needed to try it.</span>
            <?php endif; ?>
        </p>
    </section>

    <section class="steps" aria-label="How it works">
        <div class="step">
            <span class="num">1</span>
            <h2>Add your CV</h2>
            <p>Upload a PDF or paste the text.</p>
        </div>
        <div class="step">
            <span class="num">2</span>
            <h2>Add the job post</h2>
            <p>Paste the ad's text, in Danish or English.</p>
        </div>
        <div class="step">
            <span class="num">3</span>
            <h2>See where you stand</h2>
            <p>Each requirement marked met, partly met or missing, and your score.</p>
        </div>
    </section>

    <section class="plans">
        <h2>Free, or better with an account</h2>
        <div class="plan-grid">
            <div class="plan">
                <h3>Keyword match <span class="badge">free</span></h3>
                <p class="muted">Without signing in.</p>
                <ul>
                    <li>Finds the skills, tools and job titles the post names, from a vocabulary of about 11,000 Danish and English terms</li>
                    <li>Checks your CV for each one, synonyms and small typos included</li>
                    <li>Compares the years of experience asked for with the years in your CV</li>
                    <li>A quick first look: it misses requirements written as whole sentences</li>
                </ul>
            </div>
            <div class="plan featured">
                <h3>AI match <span class="badge">your own API key</span></h3>
                <p class="muted">Sign up and add your OpenAI or Anthropic API key. It runs on your key, so you pay the provider directly for what you use.</p>
                <ul>
                    <li>An AI reads the whole post, so requirements written as sentences, levels ("fluent Danish") and alternatives count too</li>
                    <li>It judges what keywords can't, like whether Vue experience partly covers React</li>
                    <li>Fetches the job post from a link</li>
                    <li>Saves your matches and writes a job application from your CV, ready to copy</li>
                </ul>
                <?php if (!$signed_in): ?>
                    <a class="button primary" href="register">Create an account</a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="employers">
        <h2>For employers</h2>
        <p>The same matching will rank every applicant against your job post, so you read the strongest applications first. It's in the works; candidates can use the CV match today.</p>
    </section>
</main>

<footer class="site-footer muted">
    Recruiter · The free match keeps nothing. Signed in, a match is saved to your account so you can come back to it.
</footer>
</body>
</html>
