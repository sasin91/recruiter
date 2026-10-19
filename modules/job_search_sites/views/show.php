<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Job Search Site Details
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
                <div class="detail-label">Domain</div>
                <div class="detail-value"><?= out($domain) ?></div>
            </div>
        </div>
    </div>
</div>
