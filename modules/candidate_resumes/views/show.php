<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Candidate Résumé Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Candidate Id</div>
                <div class="detail-value"><?= out($candidate_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Version</div>
                <div class="detail-value"><?= out($version) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Cv Name</div>
                <div class="detail-value"><?= out($cv_name) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Cv Text</div>
                <div class="detail-content"><?= nl2br(out($cv_text)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Current Title</div>
                <div class="detail-value"><?= out($current_title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Postal Code</div>
                <div class="detail-value"><?= out($postal_code) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Experience Years</div>
                <div class="detail-value"><?= out($experience_years) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Language</div>
                <div class="detail-value"><?= out($language) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Extractor</div>
                <div class="detail-value"><?= out($extractor) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Confirmed At</div>
                <div class="detail-value"><?= out($confirmed_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created At</div>
                <div class="detail-value"><?= out($created_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Updated At</div>
                <div class="detail-value"><?= out($updated_at) ?></div>
            </div>
        </div>
    </div>
</div>
