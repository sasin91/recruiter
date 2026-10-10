<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Match Score Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Job Post Id</div>
                <div class="detail-value"><?= out($job_post_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Application Id</div>
                <div class="detail-value"><?= out($job_application_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Job Post Version</div>
                <div class="detail-value"><?= out($job_post_version) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Ranking Version</div>
                <div class="detail-value"><?= out($ranking_version) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Deterministic Score</div>
                <div class="detail-value"><?= out($deterministic_score) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Llm Score</div>
                <div class="detail-value"><?= out($llm_score) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Combined Score</div>
                <div class="detail-value"><?= out($combined_score) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Tag</div>
                <div class="detail-value"><?= out($tag) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Laya Requirements Probability</div>
                <div class="detail-value"><?= out($laya_requirements_probability) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Laya Fit Expected</div>
                <div class="detail-value"><?= out($laya_fit_expected) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Laya Choice</div>
                <div class="detail-value"><?= out($laya_choice) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Needs Human</div>
                <div class="detail-value"><?= out($needs_human) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Final Rank</div>
                <div class="detail-value"><?= out($final_rank) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Computed At</div>
                <div class="detail-value"><?= out($computed_at) ?></div>
            </div>
        </div>
    </div>
</div>
