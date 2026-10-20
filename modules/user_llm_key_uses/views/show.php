<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        User AI Key Use Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor('user_llm_key_uses/delete_conf/'.$update_id, 'Delete',  array('class' => 'button danger')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Trongate User Id</div>
                <div class="detail-value"><?= out($trongate_user_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Purpose</div>
                <div class="detail-value"><?= out($purpose) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Enabled</div>
                <div class="detail-value"><?= out($enabled) ?></div>
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
