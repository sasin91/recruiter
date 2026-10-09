<?php
/**
 * The top of the candidate's pages: $title, and $candidate (or null when
 * nobody is signed in as a candidate).
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= out($title) ?> · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
    <link rel="stylesheet" href="company_module/css/company.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav>
        <?php if ($candidate): ?>
            <a href="applications">My applications</a>
            <a href="cv_match">CV match</a>
            <a href="login/logout">Sign out</a>
        <?php else: ?>
            <a href="sign-in">Sign in</a>
        <?php endif; ?>
    </nav>
</header>
