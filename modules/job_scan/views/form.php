<?php
$title = $id ? 'Edit job search' : 'New job search';
require __DIR__ . '/header.php';
?>
<main class="form">
    <p><a href="<?= $id ? 'job_scan/search/' . (int) $id : 'job_scan' ?>">&larr; Back</a></p>
    <h1><?= $id ? 'Edit job search' : 'New job search' ?></h1>

    <?= validation_errors() ?>

    <?= form_open('job_scan/submit/' . (int) $id, ['class' => 'stack']) ?>
        <?= form_label('Name', ['for' => 'name']) ?>
        <?= form_input('name', $search['name'], ['id' => 'name', 'required' => true, 'maxlength' => 120, 'placeholder' => 'Backend jobs in Odense']) ?>

        <?= form_label('What to look for', ['for' => 'keywords']) ?>
        <?= form_input('keywords', $search['keywords'], ['id' => 'keywords', 'required' => true, 'maxlength' => 500, 'placeholder' => 'PHP udvikler, backend developer']) ?>
        <span class="muted small">Job titles or keywords, separated by commas.</span>

        <?= form_label('Where', ['for' => 'location']) ?>
        <?= form_input('location', $search['location'], ['id' => 'location', 'maxlength' => 255, 'placeholder' => 'Odense, or a postcode']) ?>

        <label class="check"><?= form_checkbox('remote_ok', '1', (int) $search['remote_ok'] === 1) ?> Remote jobs too</label>

        <?= form_label('Only these sites (optional)', ['for' => 'sites']) ?>
        <?= form_textarea('sites', implode("\n", $search['sites']), ['id' => 'sites', 'rows' => 3, 'placeholder' => "jobnet.dk\njobindex.dk"]) ?>
        <span class="muted small">One site per line. Leave it empty to look on the usual Danish job sites and company career pages.</span>

        <?= form_label('CV name', ['for' => 'cv_name']) ?>
        <?= form_input('cv_name', $search['cv_name'], ['id' => 'cv_name', 'maxlength' => 255, 'placeholder' => 'cv_backend.pdf']) ?>

        <?= form_label('CV', ['for' => 'cv_text']) ?>
        <?= form_textarea('cv_text', $search['cv_text'], ['id' => 'cv_text', 'rows' => 12, 'required' => true]) ?>
        <span class="muted small">The CV every post is matched with<?= $id ? '' : ' (filled in from your latest CV match)' ?>.</span>

        <?= form_label('Tailor a résumé from this score', ['for' => 'tailor_from_score']) ?>
        <?= form_number('tailor_from_score', (int) $search['tailor_from_score'], ['id' => 'tailor_from_score', 'min' => 0, 'max' => 100]) ?>
        <span class="muted small">0 to 100. Matches scoring at least this get a tailored résumé right away; 60 is a medium match, 80 a good one, 0 tailors every match.</span>

        <?= form_label('How often', ['for' => 'scan_every_hours']) ?>
        <?= form_dropdown('scan_every_hours', $scan_every, (int) $search['scan_every_hours'], ['id' => 'scan_every_hours']) ?>

        <label class="check"><?= form_checkbox('active', '1', (int) $search['active'] === 1) ?> Scan this search</label>

        <?= form_submit('submit', 'Save job search', ['class' => 'button primary']) ?>
    <?= form_close() ?>

    <?php if ($id): ?>
        <h2>Delete it</h2>
        <p class="muted">The posts it found go too. Their saved matches stay in your CV match list.</p>
        <?= form_open('job_scan/submit_delete/' . (int) $id) ?>
            <?= form_submit('delete', 'Delete job search', ['class' => 'button']) ?>
        <?= form_close() ?>
    <?php endif; ?>
</main>
</body>
</html>
