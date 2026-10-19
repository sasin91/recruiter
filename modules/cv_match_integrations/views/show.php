<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        CV Match Integration Details
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
                <div class="detail-label">Integration</div>
                <div class="detail-value"><?= out($integration) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= out($status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">External Id</div>
                <div class="detail-value"><?= out($external_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Logged At</div>
                <div class="detail-value"><?= out($logged_at) ?></div>
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
