<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Scanned Job Post Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Job Search Id</div>
                <div class="detail-value"><?= out($job_search_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Search Run Id</div>
                <div class="detail-value"><?= out($job_search_run_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">URL</div>
                <div class="detail-value"><?= out($url) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Title</div>
                <div class="detail-value"><?= out($title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Company</div>
                <div class="detail-value"><?= out($company) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Location</div>
                <div class="detail-value"><?= out($location) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= out($status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Error</div>
                <div class="detail-value"><?= out($error) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">CV Match Id</div>
                <div class="detail-value"><?= out($cv_match_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Found At</div>
                <div class="detail-value"><?= out($found_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Matched At</div>
                <div class="detail-value"><?= out($matched_at) ?></div>
            </div>
        </div>
    </div>
</div>
