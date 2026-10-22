<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        User Profile Link Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">User Id</div>
                <div class="detail-value"><?= out($trongate_user_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Label</div>
                <div class="detail-value"><?= out($label) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">URL</div>
                <div class="detail-value"><?= out($url) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Position</div>
                <div class="detail-value"><?= out($position) ?></div>
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
