<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up your company · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
    <style>
        .cvr-row { display: flex; gap: 8px; }
        .cvr-row input { flex: 1; min-width: 0; }
        .stack .cvr-row .button { margin-top: 0; }
        #cvr-result.found { color: var(--text); }
        #cvr-result.problem { color: var(--error); }
    </style>
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

    <?= form_open($form_location, ['class' => 'stack', 'id' => 'register-form']) ?>
        <?= form_label('CVR number', ['for' => 'cvr']) ?>
        <div class="cvr-row">
            <?= form_input('cvr', $cvr, ['id' => 'cvr', 'inputmode' => 'numeric', 'autocomplete' => 'off', 'required' => true, 'placeholder' => '8 digits', 'autofocus' => true]) ?>
            <button type="button" class="button" id="cvr-find" hidden>Find</button>
        </div>
        <p class="muted small" id="cvr-result" role="status" aria-live="polite">We fill in the company's name from the CVR register.</p>

        <?= form_label('Company name', ['for' => 'company_name']) ?>
        <?= form_input('company_name', $company_name, ['id' => 'company_name', 'autocomplete' => 'organization', 'required' => true]) ?>

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
<script type="module" src="company_module/js/register.js"></script>
</body>
</html>
