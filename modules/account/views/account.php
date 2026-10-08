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
                <?= form_open('account/submit_delete') ?>
                    <?= form_submit('delete', 'Delete key', ['class' => 'button']) ?>
                <?= form_close() ?>
            </div>
            <h2>Replace it</h2>
        <?php endif; ?>

        <?= validation_errors() ?>

        <?= form_open('account/submit_key', ['class' => 'stack', 'autocomplete' => 'off']) ?>
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
</main>
</body>
</html>
