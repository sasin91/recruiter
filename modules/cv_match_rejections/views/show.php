<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        CV Match Rejection Details
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
