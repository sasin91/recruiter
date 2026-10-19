<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Job Search Run Details
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
                <div class="detail-label">Started At</div>
                <div class="detail-value"><?= out($started_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Finished At</div>
                <div class="detail-value"><?= out($finished_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Posts Found</div>
                <div class="detail-value"><?= out($posts_found) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Posts New</div>
                <div class="detail-value"><?= out($posts_new) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Input Tokens</div>
                <div class="detail-value"><?= out($input_tokens) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Output Tokens</div>
                <div class="detail-value"><?= out($output_tokens) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Error</div>
                <div class="detail-value"><?= out($error) ?></div>
            </div>
        </div>
    </div>
</div>
