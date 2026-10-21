<?php
/**
 * The application and résumé panel of a saved match: $documents
 * (Match_documents), a box per document (Document_box). js/app.js runs the
 * buttons: Write, Copy, Download PDF and Apply, and fills in the Jobnet
 * joblog when it is opened.
 */
?>
<?php if ($documents['error'] !== ''): ?>
    <p class="error"><?= out($documents['error']) ?></p>
<?php else: ?>
    <p class="muted"><?= out(Match_documents::INTRO) ?></p>
    <label class="notes"><?= out(Match_documents::NOTES_LABEL) ?><textarea rows="3" maxlength="<?= Match_documents::NOTES_MAX ?>" placeholder="<?= out(Match_documents::NOTES_HINT) ?>"></textarea></label>
    <?php foreach ($documents['boxes'] as $box): ?>
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
            <div class="keywords" hidden></div>
            <p class="compare" hidden></p>
            <textarea rows="18"<?= $box['text'] === '' ? ' hidden' : '' ?>><?= out($box['text']) ?></textarea>
        </div>
    <?php endforeach; ?>
    <div class="actions apply-open">
        <button type="button" class="apply" data-id="<?= $documents['id'] ?>"><span class="label">Apply</span></button>
    </div>
    <div class="joblog-slot" data-id="<?= $documents['id'] ?>"></div>
<?php endif; ?>
