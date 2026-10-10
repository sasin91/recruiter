<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Term Relation Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Term Id</div>
                <div class="detail-value"><?= out($term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Related Term Id</div>
                <div class="detail-value"><?= out($related_term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Relation</div>
                <div class="detail-value"><?= out($relation) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Confidence</div>
                <div class="detail-value"><?= out($confidence) ?></div>
            </div>
        </div>
    </div>
</div>
