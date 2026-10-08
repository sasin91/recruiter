<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up your company · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav><a href="company-sign-in">Company sign-in</a></nav>
</header>
<main class="narrow">
    <h1>Sign up your company</h1>
    <p class="muted">Post jobs and read every application ranked against what you ask for. You'll be the company's owner and can invite colleagues.</p>

    <?= validation_errors() ?>

    <?= form_open($form_location, ['class' => 'stack']) ?>
        <?= form_label('Company name', ['for' => 'company_name']) ?>
        <?= form_input('company_name', $company_name, ['id' => 'company_name', 'autocomplete' => 'organization', 'required' => true]) ?>

        <?= form_label('CVR number', ['for' => 'cvr']) ?>
        <?= form_input('cvr', $cvr, ['id' => 'cvr', 'inputmode' => 'numeric', 'required' => true, 'placeholder' => '8 digits']) ?>

        <?= form_label('Your name', ['for' => 'name']) ?>
        <?= form_input('name', $name, ['id' => 'name', 'autocomplete' => 'name', 'required' => true]) ?>

        <?= form_label('Work email', ['for' => 'email']) ?>
        <?= form_email('email', $email, ['id' => 'email', 'autocomplete' => 'email', 'required' => true]) ?>

        <?= form_label('Password', ['for' => 'password']) ?>
        <?= form_password('password', '', ['id' => 'password', 'autocomplete' => 'new-password', 'minlength' => 8, 'required' => true]) ?>

        <?= form_label('Repeat password', ['for' => 'password_repeat']) ?>
        <?= form_password('password_repeat', '', ['id' => 'password_repeat', 'autocomplete' => 'new-password', 'minlength' => 8, 'required' => true]) ?>

        <?= form_submit('submit', 'Sign up', ['class' => 'button primary']) ?>
    <?= form_close() ?>

    <p class="muted">Already signed up? <a href="company-sign-in">Sign in</a>. Looking for a job? <a href="register">Create a candidate account</a>.</p>
</main>
</body>
</html>
