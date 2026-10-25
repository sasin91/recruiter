<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your account · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav>
        <a href="cv_match">CV match</a>
        <a href="job_scan">Job scan</a>
        <a href="login/logout">Sign out</a>
    </nav>
</header>
<main class="narrow">
    <h1>Your AI key</h1>
    <p class="muted">The AI match runs on your own OpenAI or Anthropic API key, so you pay the provider directly for what you use.</p>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <?php if ($is_admin): ?>
        <p class="notice">You're an administrator: without a key of your own, the AI match uses the server's key.</p>
    <?php endif; ?>

    <?php if (!$vault_ready): ?>
        <p class="notice">Saving keys isn't set up on this server yet.</p>
    <?php else: ?>
        <?php if ($saved): ?>
            <div class="saved-key">
                <p><strong><?= out($providers[$saved['provider']] ?? $saved['provider']) ?></strong> key ending in <code>…<?= out($saved['key_hint']) ?></code><?= $saved['model'] !== '' ? ', model <code>' . out($saved['model']) . '</code>' : '' ?>.<br>
                <span class="muted">Saved <?= date('j M Y', (int) $saved['updated_at']) ?>.</span></p>
                <?php if ($unreadable): ?>
                    <p class="notice">This key can't be read on this server anymore, so the AI match can't use it. Save it again below.</p>
                <?php endif; ?>
                <?php if (!$unreadable): ?>
                    <?= form_open('account/submit_uses', ['class' => 'stack key-uses']) ?>
                        <fieldset>
                            <legend>Use my key for</legend>
                            <?php foreach ($uses as $use => $label): ?>
                                <label><?= form_checkbox("use_for_$use", 1, $saved['uses'][$use]) ?> <?= out($label) ?></label>
                            <?php endforeach; ?>
                            <span class="muted small">Switched off, the CV match reads job posts with the free reader instead of the AI, and résumés or applications aren't written.</span>
                        </fieldset>
                        <?= form_submit('save_uses', 'Save', ['class' => 'button']) ?>
                    <?= form_close() ?>
                <?php endif; ?>
                <?= form_open('account/submit_delete') ?>
                    <?= form_submit('delete', 'Delete key', ['class' => 'button']) ?>
                <?= form_close() ?>
            </div>
            <h2>Replace it</h2>
        <?php endif; ?>

        <?= validation_errors('FORM-key') ?>

        <?= form_open('account/submit_key', ['class' => 'stack', 'autocomplete' => 'off']) ?>
            <?= form_hidden('form_name', 'key') ?>
            <?= form_label('Provider', ['for' => 'provider']) ?>
            <?= form_dropdown('provider', $providers, $provider ?: ($saved['provider'] ?? 'openai'), ['id' => 'provider']) ?>

            <?= form_label('API key', ['for' => 'api_key']) ?>
            <?= form_password('api_key', '', ['id' => 'api_key', 'autocomplete' => 'off', 'required' => true, 'placeholder' => 'sk-…']) ?>
            <span class="muted small">From <a href="https://platform.openai.com/api-keys" target="_blank" rel="noopener">platform.openai.com/api-keys</a> or <a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noopener">console.anthropic.com</a>. It's stored encrypted and never shown again.</span>

            <?= form_label('Model (optional)', ['for' => 'model']) ?>
            <?= form_input('model', $model ?: ($saved['model'] ?? ''), ['id' => 'model', 'placeholder' => 'Default: gpt-5.4-mini or claude-sonnet-5-5']) ?>

            <?= form_submit('submit', 'Save key', ['class' => 'button primary']) ?>
        <?= form_close() ?>
    <?php endif; ?>

    <?php if ($has_profile_links): ?>
    <h2>Your profile</h2>
    <p class="muted">Your job applications and tailored résumés are written from <a href="profile">your profile</a>: fill it in from your CV, then add your own projects.</p>
    <section class="profile-links">
        <h2 id="profile-links">Your profile links</h2>
        <p class="muted">Links job applications ask for, like LinkedIn or GitHub. The Apply dialog of a saved match has them ready to copy. Leave a link empty to remove it.</p>
        <?php if ($link_errors): ?>
            <ul class="validation-errors">
                <?php foreach ($link_errors as $error): ?>
                    <li><?= out($error) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <?= form_open('account/submit_links', ['class' => 'stack']) ?>
            <?php foreach ($links as $i => $link): ?>
                <div class="profile-link-row">
                    <?= form_input('label[]', $link['label'], ['aria-label' => 'Name of link ' . ($i + 1), 'placeholder' => 'Name', 'maxlength' => '64']) ?>
                    <?= form_input('url[]', $link['url'], ['aria-label' => 'Address of link ' . ($i + 1), 'placeholder' => 'https://…', 'inputmode' => 'url', 'maxlength' => '512']) ?>
                </div>
            <?php endforeach; ?>
            <?= form_submit('save_links', 'Save links', ['class' => 'button primary']) ?>
        <?= form_close() ?>
    </section>
    <?php endif; ?>

    <?php if ($is_candidate): ?>
    <section class="your-data narrow-section">
        <h2 id="your-data">Your data</h2>
        <p class="muted">Everything Recruiter keeps about you, as <a href="privacy#what">the privacy page</a> lists it: your account, profile, matches, applications and job searches. Passwords and keys are left out.</p>
        <p><a class="button" href="account/download_data">Download my data</a></p>
        <?php if (!$is_admin): ?>
            <details<?= $delete_failed ? ' open' : '' ?>>
                <summary>Delete my account</summary>
                <p class="muted">Deletes your account and everything in it at once: profile, CVs, matches, written applications and résumés, job searches and your API key. It can't be undone. Download your data first if you want a copy.</p>
                <?= validation_errors('FORM-delete_account') ?>
                <?= form_open('account/submit_delete_account', ['class' => 'stack']) ?>
                    <?= form_hidden('form_name', 'delete_account') ?>
                    <?= form_label('Your password', ['for' => 'delete_password']) ?>
                    <?= form_password('password', '', ['id' => 'delete_password', 'autocomplete' => 'current-password', 'required' => true]) ?>
                    <label><?= form_checkbox('confirm', 1, false, ['required' => true]) ?> Yes, delete my account and everything in it</label>
                    <?= form_submit('delete_account', 'Delete my account', ['class' => 'button']) ?>
                <?= form_close() ?>
            </details>
        <?php endif; ?>
    </section>
    <?php endif; ?>
</main>
<footer class="site-footer muted"><a href="privacy">Privacy</a></footer>
</body>
</html>
