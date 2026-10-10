<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        CV Match Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor('cv_matches/delete_conf/'.$update_id, 'Delete',  array('class' => 'button danger')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Trongate User Id</div>
                <div class="detail-value"><?= out($trongate_user_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Title</div>
                <div class="detail-value"><?= out($job_title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Company</div>
                <div class="detail-value"><?= out($company) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Url</div>
                <div class="detail-value"><?= out($job_url) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Job Text</div>
                <div class="detail-content"><?= nl2br(out($job_text)) ?></div>
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
                <div class="detail-label">Score</div>
                <div class="detail-value"><?= out($score) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Points</div>
                <div class="detail-value"><?= out($points) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Max Points</div>
                <div class="detail-value"><?= out($max_points) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Tag</div>
                <div class="detail-value"><?= out($tag) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Application Text</div>
                <div class="detail-content"><?= nl2br(out($application_text)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Application Written At</div>
                <div class="detail-value"><?= out($application_written_at) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Resume Text</div>
                <div class="detail-content"><?= nl2br(out($resume_text)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Resume Written At</div>
                <div class="detail-value"><?= out($resume_written_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created At</div>
                <div class="detail-value"><?= out($created_at) ?></div>
            </div>
        </div>
    </div>
</div>
