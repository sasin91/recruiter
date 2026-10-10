<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Application Action Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Job Application Id</div>
                <div class="detail-value"><?= out($job_application_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Company Member Id</div>
                <div class="detail-value"><?= out($company_member_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Candidate Id</div>
                <div class="detail-value"><?= out($candidate_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Action</div>
                <div class="detail-value"><?= out($action) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">From Status</div>
                <div class="detail-value"><?= out($from_status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">To Status</div>
                <div class="detail-value"><?= out($to_status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Post Version</div>
                <div class="detail-value"><?= out($job_post_version) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Match Score Id</div>
                <div class="detail-value"><?= out($match_score_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Final Rank</div>
                <div class="detail-value"><?= out($final_rank) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Source</div>
                <div class="detail-value"><?= out($source) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reason</div>
                <div class="detail-value"><?= out($reason) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Note</div>
                <div class="detail-value"><?= out($note) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created At</div>
                <div class="detail-value"><?= out($created_at) ?></div>
            </div>
        </div>
    </div>
</div>
