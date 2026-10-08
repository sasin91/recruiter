<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Join your company · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav><a href="company-sign-in">Company sign-in</a></nav>
</header>
<main class="narrow">
    <?php if ($invitation === null): ?>
        <h1>This invite link no longer works</h1>
        <p class="muted">It has been used or withdrawn. If you've already chosen a password, <a href="company-sign-in">sign in</a>. Otherwise ask your colleague for a new link.</p>
    <?php else: ?>
        <h1>Join <?= out($invitation['company_name']) ?></h1>
        <p class="muted">Hi <?= out($invitation['name']) ?>. Choose a password; you'll sign in with <strong><?= out($invitation['email']) ?></strong>.</p>

        <?= validation_errors() ?>

        <?= form_open($form_location, ['class' => 'stack']) ?>
            <?= form_label('Password', ['for' => 'password']) ?>
            <?= form_password('password', '', ['id' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8, 'required' => true]) ?>

            <?= form_label('Repeat password', ['for' => 'password_repeat']) ?>
            <?= form_password('password_repeat', '', ['id' => 'password_repeat', 'autocomplete' => 'new-password', 'minlength' => 8, 'required' => true]) ?>

            <?= form_submit('submit', 'Join', ['class' => 'button primary']) ?>
        <?= form_close() ?>
    <?php endif; ?>
</main>
</body>
</html>
