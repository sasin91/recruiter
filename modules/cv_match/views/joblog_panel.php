<?php
/**
 * The Jobnet joblog entry of a saved match: $panel (Joblog_panel).
 * cv_match/joblog_panel answers with it when the joblog is opened; js/app.js
 * puts it in the panel, fills in the company's registered address from the
 * CVR register when the post has none, and runs the buttons.
 */
?>
<?php foreach ($panel['sections'] as $section): ?>
    <section class="joblog-section">
        <h5><?= out($section['heading']) ?></h5>
        <?php foreach ($section['fields'] as $field): ?>
            <div class="joblog-field" data-name="<?= out($field['name']) ?>" data-label="<?= out($field['label']) ?>">
                <span class="joblog-label"><?= out($field['label']) ?><?php if ($field['required']): ?><span class="required" aria-hidden="true">*</span><?php endif; ?></span>
                <?php if ($field['choice']): ?>
                    <div class="joblog-options">
                        <?php foreach ($field['options'] as $option): ?>
                            <?php if ($field['choose']): ?>
                                <button type="button" class="chip<?= $option['chosen'] ? ' chosen' : '' ?>" aria-pressed="<?= $option['chosen'] ? 'true' : 'false' ?>" data-value="<?= out($option['value']) ?>" data-prompt="<?= out($option['prompt']) ?>"><?= out($option['text']) ?></button>
                            <?php else: ?>
                                <span class="chip<?= $option['chosen'] ? ' chosen' : '' ?>" data-value="<?= out($option['value']) ?>" data-prompt="<?= out($option['prompt']) ?>"><?= out($option['text']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                    <small class="muted hint"<?= $field['hint'] === '' ? ' hidden' : '' ?>><?= out($field['hint']) ?></small>
                <?php else: ?>
                    <div class="joblog-value">
                        <?php if ($field['multiline']): ?>
                            <textarea rows="4" maxlength="<?= Joblog::MAX_NOTES ?>" aria-label="<?= out($field['label']) ?>"><?= out($field['value']) ?></textarea>
                        <?php else: ?>
                            <input type="text" value="<?= out($field['value']) ?>" inputmode="<?= $field['numeric'] ? 'numeric' : 'text' ?>" aria-label="<?= out($field['label']) ?>">
                        <?php endif; ?>
                        <button type="button" class="copy-field">Copy</button>
                    </div>
                    <small class="muted hint"<?= $field['hint'] === '' ? ' hidden' : '' ?>><?= out($field['hint']) ?></small>
                    <?php if ($field['left'] !== null): ?>
                        <small class="muted"><?= out(Joblog_panel::CHARACTERS_LEFT) ?> <span class="left"><?= $field['left'] ?></span></small>
                    <?php endif; ?>
                    <span class="muted status" aria-live="polite"></span>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>
<section class="joblog-section">
    <h5>Gem ansøgning, CV og jobannonce</h5>
    <div class="actions">
        <?php // Not disabled, since run() enables every button when a task ends. ?>
        <?php foreach ($panel['files'] as $file): ?>
            <button type="button" class="joblog-pdf<?= $file['ready'] ? '' : ' unavailable' ?>" data-kind="<?= out($file['kind']) ?>" title="<?= out($file['title']) ?>"><span class="label"><?= out($file['label']) ?></span></button>
        <?php endforeach; ?>
    </div>
    <?php foreach ($panel['missing'] as $missing): ?>
        <p class="muted"><?= out($missing) ?></p>
    <?php endforeach; ?>
</section>
<section class="joblog-section">
    <h5>Fill it in with an AI</h5>
    <p class="muted">Copies these values as instructions for an AI that can use your browser, such as Claude in Chrome. It fills the form and stops before Gem; you add the PDFs.</p>
    <div class="actions"><button type="button" class="joblog-prompt">Copy as AI prompt</button><span class="muted status" aria-live="polite"></span></div>
    <?php // Shown once copied, so the prompt can be read, and copied by hand if the clipboard refuses. ?>
    <textarea class="joblog-prompt-text" rows="8" readonly hidden></textarea>
</section>
<?php $logged = $panel['logged']; require __DIR__ . '/joblog_logged.php'; ?>
