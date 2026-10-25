<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav><a href="cv_match">CV match</a></nav>
</header>
<main class="prose">
    <h1>Privacy</h1>
    <p class="muted">Last changed <?= out($updated) ?>.</p>

    <p>Recruiter keeps the CVs, profiles and job applications you give it so it can match you with jobs and write applications for you. This page says what it keeps, who sees it, how long, and how to get it back or deleted.</p>

    <h2 id="who">Who is responsible</h2>
    <?php if ($owner !== '' && $email !== ''): ?>
        <p>Recruiter is run by <?= out($owner) ?>, the data controller under the GDPR. Write to <a href="mailto:<?= out($email) ?>"><?= out($email) ?></a> about anything on this page.</p>
    <?php else: ?>
        <p>Recruiter is run by its site owner, the data controller under the GDPR. Report a problem on <a href="https://github.com/sasin91/recruiter/issues" rel="noopener">Recruiter's GitHub issues</a> (not with personal details) and ask for a way to reach them.</p>
    <?php endif; ?>

    <h2 id="free-match">Without an account</h2>
    <p>The free match keeps nothing. The CV and job post you paste are read on the server, a summary of the skills found goes to Recruiter's own ranking model on the same server, and both are gone when the answer is sent. To stop one visitor from using up the ranking model, a scrambled (hashed) form of your IP address and the times of your last matches are kept in a temporary file, which goes when the server restarts.</p>

    <h2 id="what">What is kept with an account</h2>
    <ul>
        <li><strong>Your account:</strong> name, email, a hash of your password (never the password), and the phone number and postcode if you give them.</li>
        <li><strong>Your CV and profile:</strong> the CV text you paste or upload (PDFs are read in your browser; only their text is sent), and the profile it fills in: roles, dates, education, projects, skills, languages and the links you add.</li>
        <li><strong>Your matches:</strong> each job post you match, the CV it was matched with, the score and why, and the applications and résumés written for it.</li>
        <li><strong>Your job hunt:</strong> saved job searches and the posts they find, which jobs you applied for and when, their status in your Jobnet joblog, and the reasons and notes you give when you reject a job.</li>
        <li><strong>Your AI key:</strong> your OpenAI or Anthropic API key, encrypted. Only its last four characters are ever shown.</li>
        <li><strong>Signing in:</strong> a sign-in token (so you stay signed in for 30 days), password reset links, and the IP address of failed sign-ins for about 15 minutes, against password guessing.</li>
    </ul>
    <p>Please leave out of your CV and notes what nobody needs to judge your application: your CPR number, health, union or a-kasse membership, religion and photos.</p>

    <h2 id="why">Why</h2>
    <p>To give you the service you signed up for (GDPR art. 6(1)(b)): matching, writing, saving and finding jobs. Sign-in records are kept to protect your account (art. 6(1)(f), a legitimate interest). Your data is never sold, used for ads or used to train models.</p>

    <h2 id="recipients">Who else gets it</h2>
    <ul>
        <li><strong>OpenAI or Anthropic</strong>, when you use the AI features: the CV, profile and job post go to the provider on your own API key, so under your own account and that provider's terms. They may process it in the USA. Recruiter asks OpenAI not to store it.</li>
        <li><strong>Hetzner</strong> (Germany and Finland) hosts the server and its database, in the EU.</li>
        <li><strong>A company you apply to through Recruiter</strong> sees your application and the CV you send with it.</li>
        <li><strong>cvrapi.dk</strong> gets a company's name or CVR number from your browser when Recruiter looks up its address. Nothing about you.</li>
        <li>The <a href="joblog_extension#privacy">joblog browser extension</a> has its own short policy; it only moves your own matches into Jobnet's form.</li>
    </ul>

    <h2 id="how-long">How long</h2>
    <p>As long as you have your account. What you delete (a match, a profile entry, your API key, or your whole account) is gone from the database at once. Copies taken before database maintenance are kept for up to 7 days.</p>

    <h2 id="rights">Your rights</h2>
    <p>You can see, correct and delete what you entered on your pages. Under <a href="account#your-data">Your data</a> on your account page you can download a copy of everything kept about you, and delete your account and everything in it.</p>
    <p>For anything else, like objecting to how your data is used, write to the address above and you get an answer within a month.</p>
    <p>If you think your data is mishandled, you can complain to <a href="https://www.datatilsynet.dk" rel="noopener">Datatilsynet</a>, the Danish data protection authority.</p>

    <h2 id="cookies">Cookies</h2>
    <p>Only the ones the site needs to work: <code>trongatetoken</code> keeps you signed in (30 days), a session cookie remembers your place while you use the site, and <code>app_lang</code> your language. No analytics, no ads, no tracking.</p>
</main>
</body>
</html>
