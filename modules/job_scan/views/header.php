<?php
/**
 * The top of every job scan page: $title.
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
    <link rel="stylesheet" href="job_scan_module/css/job_scan.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav>
        <a href="cv_match">CV match</a>
        <a href="job_scan">Job scan</a>
        <a href="account">Account</a>
        <a href="login/logout">Sign out</a>
    </nav>
</header>
<?php Modules::run('joblog_extension/_banner'); ?>
