<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Match Score Detail Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Match Score Id</div>
                <div class="detail-value"><?= out($match_score_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Stage</div>
                <div class="detail-value"><?= out($stage) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Criterion</div>
                <div class="detail-value"><?= out($criterion) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Post Term Id</div>
                <div class="detail-value"><?= out($job_post_term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Application Term Id</div>
                <div class="detail-value"><?= out($job_application_term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Rule</div>
                <div class="detail-value"><?= out($rule) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Weight</div>
                <div class="detail-value"><?= out($weight) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Rank</div>
                <div class="detail-value"><?= out($rank) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Passed</div>
                <div class="detail-value"><?= out($passed) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Probability</div>
                <div class="detail-value"><?= out($probability) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reason</div>
                <div class="detail-value"><?= out($reason) ?></div>
            </div>
        </div>
    </div>
</div>
