<?php
$title = 'Settings';
require __DIR__ . '/header.php';
?>
<main class="narrow">
    <h1>Settings</h1>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?= validation_errors() ?>

    <?= form_open('company/submit_settings', ['class' => 'stack']) ?>
        <?= form_label('Company name', ['for' => 'company_name']) ?>
        <?= form_input('company_name', $company_name, ['id' => 'company_name', 'required' => true]) ?>

        <?= form_label('Contact email', ['for' => 'contact_email']) ?>
        <?= form_email('contact_email', $contact_email, ['id' => 'contact_email', 'required' => true]) ?>
        <span class="muted small">Candidates see it on your job posts and in replies to their applications.</span>

        <?= form_submit('submit', 'Save', ['class' => 'button primary']) ?>
    <?= form_close() ?>

    <h2>The company's AI key</h2>
    <p class="muted">Reading job posts and judging applications run on the company's own OpenAI or Anthropic API key, so you pay the provider directly for what you use. Without a key, applications get the free keyword match only.</p>

    <?php if (!$vault_ready): ?>
        <p class="notice">Saving keys isn't set up on this server yet.</p>
    <?php else: ?>
        <?php if ($saved): ?>
            <div class="saved-key">
                <p><strong><?= out($providers[$saved['provider']] ?? $saved['provider']) ?></strong> key ending in <code>…<?= out($saved['key_hint']) ?></code><?= $saved['model'] !== '' ? ', model <code>' . out($saved['model']) . '</code>' : '' ?>.<br>
                <span class="muted">Saved <?= date('j M Y', (int) $saved['updated_at']) ?>.</span></p>
                <?= form_open('company/submit_delete_key') ?>
                    <?= form_submit('delete', 'Delete key', ['class' => 'button']) ?>
                <?= form_close() ?>
            </div>
            <h3>Replace it</h3>
        <?php endif; ?>

        <?= form_open('company/submit_key', ['class' => 'stack', 'autocomplete' => 'off']) ?>
            <?= form_label('Provider', ['for' => 'provider']) ?>
            <?= form_dropdown('provider', $providers, $provider ?: ($saved['provider'] ?? 'openai'), ['id' => 'provider']) ?>

            <?= form_label('API key', ['for' => 'api_key']) ?>
            <?= form_password('api_key', '', ['id' => 'api_key', 'autocomplete' => 'off', 'required' => true, 'placeholder' => 'sk-…']) ?>
            <span class="muted small">Stored encrypted and never shown again.</span>

            <?= form_label('Model (optional)', ['for' => 'model']) ?>
            <?= form_input('model', $model ?: ($saved['model'] ?? ''), ['id' => 'model', 'placeholder' => 'Default: gpt-5.4-mini or claude-sonnet-5-5']) ?>

            <?= form_submit('submit', 'Save key', ['class' => 'button primary']) ?>
        <?= form_close() ?>
    <?php endif; ?>
</main>
</body>
</html>
