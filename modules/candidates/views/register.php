<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create an account · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav><a href="sign-in">Sign in</a></nav>
</header>
<main class="narrow">
    <h1>Create an account</h1>
    <p class="muted">With an account and your own OpenAI or Anthropic API key, you get the AI match, saved matches and written job applications.</p>

    <?= validation_errors() ?>

    <?= form_open($form_location, ['class' => 'stack']) ?>
        <?= form_label('Name', ['for' => 'name']) ?>
        <?= form_input('name', $name, ['id' => 'name', 'autocomplete' => 'name', 'required' => true]) ?>

        <?= form_label('Email', ['for' => 'email']) ?>
        <?= form_email('email', $email, ['id' => 'email', 'autocomplete' => 'email', 'required' => true]) ?>

        <?= form_label('Password', ['for' => 'password']) ?>
        <?= form_password('password', '', ['id' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8, 'required' => true]) ?>

        <?= form_label('Repeat password', ['for' => 'password_repeat']) ?>
        <?= form_password('password_repeat', '', ['id' => 'password_repeat', 'autocomplete' => 'new-password', 'minlength' => 8, 'required' => true]) ?>

        <?= form_submit('submit', 'Create account', ['class' => 'button primary']) ?>
    <?= form_close() ?>

    <p class="muted">Already have an account? <a href="sign-in">Sign in</a>.</p>
</main>
</body>
</html>
