<?php
/**
 * The application and résumé panel of a saved match: $documents
 * (Match_documents), a box per document (Document_box). js/app.js runs the
 * buttons: Write, Copy, Download PDF and Apply, and reads the Jobnet
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
    <?php // Jobnet's "Opret joblog" form filled in from the match: js/app.js reads it (views/joblog_panel.php) on every opening, so a newly written application counts. ?>
    <details class="joblog" data-id="<?= $documents['id'] ?>">
        <summary>Jobnet joblog</summary>
        <p class="muted">Jobnet's "Opret joblog" form, filled in from this match: check each field, copy it over, and download the PDFs for its uploads. Empty fields weren't in the post.</p>
        <div class="joblog-body"></div>
        <template class="joblog-loading"><p class="muted">Filling in the joblog…</p></template>
        <template class="joblog-failed"><p class="error"></p></template>
    </details>
<?php endif; ?>
