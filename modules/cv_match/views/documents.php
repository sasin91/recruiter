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
        <?php require __DIR__ . '/document_box.php'; ?>
    <?php endforeach; ?>
    <div class="actions apply-open">
        <button type="button" class="apply" data-id="<?= $documents['id'] ?>"><span class="label">Apply</span></button>
    </div>
    <div class="joblog-slot" data-id="<?= $documents['id'] ?>"></div>
<?php endif; ?>
