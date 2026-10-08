<?php
/**
 * The top of every signed-in company page: $title, and $member (the
 * signed-in member, see Company::member()).
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
</head>
<body>
<header class="site-header">
    <a class="brand" href="company"><?= out($member['company_name']) ?></a>
    <nav>
        <a href="company/members">Members</a>
        <?php if ($member['role'] === 'owner'): ?>
            <a href="company/settings">Settings</a>
        <?php endif; ?>
        <a href="login/logout">Sign out</a>
    </nav>
</header>
