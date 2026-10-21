<?php
/**
 * The Apply dialog's content for a saved match: $delivery
 * (Application_delivery::from). Copy buttons name the field they copy
 * (data-copy) and PDF buttons the document (data-pdf); js/app.js handles
 * both.
 */
?>
<section class="application-delivery" data-id="<?= $delivery['id'] ?>">
    <h2 id="application-delivery-heading"><?= out($delivery['heading']) ?></h2>
    <?php if ($delivery['intro'] !== ''): ?>
        <p class="muted"><?= out($delivery['intro']) ?></p>
    <?php endif; ?>
    <?php foreach ($delivery['fields'] as $field): ?>
        <div class="joblog-field">
            <label class="joblog-label" for="<?= $field['id'] ?>"><?= out($field['label']) ?></label>
            <div class="joblog-value">
                <?php if ($field['multiline']): ?>
                    <textarea id="<?= $field['id'] ?>" rows="8"><?= out($field['value']) ?></textarea>
                <?php else: ?>
                    <input type="text" id="<?= $field['id'] ?>" value="<?= out($field['value']) ?>">
                <?php endif; ?>
                <button type="button" data-copy="<?= $field['id'] ?>">Copy</button>
            </div>
            <?php if ($field['hint'] !== ''): ?>
                <small class="muted"><?= out($field['hint']) ?></small>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <?php if ($delivery['asks'] !== ''): ?>
        <p class="apply-hint"><?= out($delivery['asks']) ?></p>
    <?php endif; ?>
    <h5><?= out($delivery['files_heading']) ?></h5>
    <div class="actions">
        <?php foreach ($delivery['files'] as $file): ?>
            <?php if ($file['ready']): ?>
                <button type="button" data-pdf="<?= $file['kind'] ?>"><span class="label"><?= out($file['label']) ?> (PDF)</span></button>
            <?php else: ?>
                <button type="button" data-pdf="<?= $file['kind'] ?>" class="unavailable" title="<?= out($file['missing']) ?>"><span class="label"><?= out($file['label']) ?> (PDF)</span></button>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php foreach ($delivery['missing'] as $missing): ?>
        <p class="muted"><?= out($missing) ?></p>
    <?php endforeach; ?>
    <?php if ($delivery['open'] !== null): ?>
        <div class="actions">
            <a class="button-link primary" href="<?= out($delivery['open']['href']) ?>" target="<?= $delivery['open']['target'] ?>" rel="noopener"><?= out($delivery['open']['label']) ?></a>
        </div>
    <?php endif; ?>
</section>
