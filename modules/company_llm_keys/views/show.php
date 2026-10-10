<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Company AI Key Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor('company_llm_keys/delete_conf/'.$update_id, 'Delete',  array('class' => 'button danger')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Company Id</div>
                <div class="detail-value"><?= out($company_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Provider</div>
                <div class="detail-value"><?= out($provider) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Model</div>
                <div class="detail-value"><?= out($model) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Key Hint</div>
                <div class="detail-value"><?= out($key_hint) ?></div>
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
