<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        CV Match Item Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Cv Match Id</div>
                <div class="detail-value"><?= out($cv_match_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Criterion</div>
                <div class="detail-value"><?= out($criterion) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Text</div>
                <div class="detail-value"><?= out($text) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Verdict</div>
                <div class="detail-value"><?= out($verdict) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Decided By</div>
                <div class="detail-value"><?= out($decided_by) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Reason</div>
                <div class="detail-value"><?= out($reason) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Evidence</div>
                <div class="detail-value"><?= out($evidence) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Sort Order</div>
                <div class="detail-value"><?= out($sort_order) ?></div>
            </div>
        </div>
    </div>
</div>
