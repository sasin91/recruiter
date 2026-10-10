<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Application Term Details
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
                <div class="detail-label">Kind</div>
                <div class="detail-value"><?= out($kind) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Term Id</div>
                <div class="detail-value"><?= out($term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Raw Text</div>
                <div class="detail-value"><?= out($raw_text) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Normalised</div>
                <div class="detail-value"><?= out($normalised) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Years</div>
                <div class="detail-value"><?= out($years) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Level</div>
                <div class="detail-value"><?= out($level) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Is Highlighted</div>
                <div class="detail-value"><?= out($is_highlighted) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Match Method</div>
                <div class="detail-value"><?= out($match_method) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Similarity</div>
                <div class="detail-value"><?= out($similarity) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Sort Order</div>
                <div class="detail-value"><?= out($sort_order) ?></div>
            </div>
        </div>
    </div>
</div>
