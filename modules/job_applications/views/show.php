<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Job Application Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor('job_applications/delete_conf/'.$update_id, 'Delete',  array('class' => 'button danger')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Job Post Id</div>
                <div class="detail-value"><?= out($job_post_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Candidate Id</div>
                <div class="detail-value"><?= out($candidate_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Current Title</div>
                <div class="detail-value"><?= out($current_title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Level</div>
                <div class="detail-value"><?= out($level) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Workplace Flexibility</div>
                <div class="detail-value"><?= out($workplace_flexibility) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Work Hours</div>
                <div class="detail-value"><?= out($work_hours) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Postal Code</div>
                <div class="detail-value"><?= out($postal_code) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Language</div>
                <div class="detail-value"><?= out($language) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Source</div>
                <div class="detail-value"><?= out($source) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Raw Text</div>
                <div class="detail-content"><?= nl2br(out($raw_text)) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Cover Letter</div>
                <div class="detail-content"><?= nl2br(out($cover_letter)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Extractor</div>
                <div class="detail-value"><?= out($extractor) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Candidate Resume Version</div>
                <div class="detail-value"><?= out($candidate_resume_version) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= out($status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Submitted At</div>
                <div class="detail-value"><?= out($submitted_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Shortlisted At</div>
                <div class="detail-value"><?= out($shortlisted_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Bookmarked At</div>
                <div class="detail-value"><?= out($bookmarked_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Rejected At</div>
                <div class="detail-value"><?= out($rejected_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reject Reason</div>
                <div class="detail-value"><?= out($reject_reason) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Withdrawn At</div>
                <div class="detail-value"><?= out($withdrawn_at) ?></div>
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
