<?php
/**
 * One document box of a saved match's application and résumé panel: $box
 * (Document_box). Printed in the panel (views/documents.php), and on its own
 * as write_application's and tailor_resume's answer, which js/app.js puts in
 * place of the box.
 */
?>
<div class="document" data-kind="<?= $box['kind'] ?>" data-id="<?= $box['id'] ?>"<?= $box['written_at'] !== '' ? ' data-written-at="' . out($box['written_at']) . '"' : '' ?> data-structured="<?= $box['structured'] ? 'true' : 'false' ?>">
    <h4><?= out($box['title']) ?></h4>
    <?php if ($box['send_to']): ?>
        <p class="apply-hint">The post asks for it by email: <a href="<?= out($box['send_to']['mailto']) ?>"><?= out($box['send_to']['email']) ?></a><?php if ($box['send_to']['subject'] !== ''): ?> with the subject <code><?= out($box['send_to']['subject']) ?></code><?php endif; ?>.</p>
    <?php endif; ?>
    <?php if ($box['off']): ?>
        <p class="muted"><?= out($box['off_note']) ?> <a href="account">Switch it on</a> on your account page.</p>
    <?php endif; ?>
    <div class="actions">
        <button type="button" class="write" data-off="<?= $box['off'] ? 'true' : 'false' ?>"<?= $box['off'] ? ' disabled' : '' ?>><span class="label"><?= out($box['write']) ?></span></button>
        <button type="button" class="copy"<?= $box['text'] === '' ? ' hidden' : '' ?>>Copy to clipboard</button>
        <button type="button" class="download-pdf"<?= $box['text'] === '' ? ' hidden' : '' ?>><span class="label">Download PDF</span></button>
        <span class="muted status" aria-live="polite"></span>
    </div>
    <?php if ($box['keywords'] || $box['left_out'] !== ''): ?>
        <div class="keywords">
            <?php if ($box['keywords']): ?>
                <details>
                    <summary><?= out($box['keywords']['summary']) ?></summary>
                    <ul>
                        <?php foreach ($box['keywords']['items'] as $keyword): ?>
                            <li><strong><?= out($keyword['term']) ?></strong><?php if ($keyword['basis'] !== ''): ?>: from <?= out($keyword['basis']) ?><?php endif; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endif; ?>
            <?php if ($box['left_out'] !== ''): ?>
                <p class="muted"><?= out($box['left_out']) ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <p class="compare" hidden></p>
    <textarea rows="18"<?= $box['text'] === '' ? ' hidden' : '' ?>><?= out($box['text']) ?></textarea>
</div>
