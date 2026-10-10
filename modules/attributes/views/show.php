<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Attribute Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Owner Type</div>
                <div class="detail-value"><?= out($owner_type) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Owner Id</div>
                <div class="detail-value"><?= out($owner_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Attr Key</div>
                <div class="detail-value"><?= out($attr_key) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Value</div>
                <div class="detail-content"><?= nl2br(out($value)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Lang</div>
                <div class="detail-value"><?= out($lang) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Sort Order</div>
                <div class="detail-value"><?= out($sort_order) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created At</div>
                <div class="detail-value"><?= out($created_at) ?></div>
            </div>
        </div>
    </div>
</div>
