<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Term Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Kind</div>
                <div class="detail-value"><?= out($kind) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Parent Id</div>
                <div class="detail-value"><?= out($parent_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Slug</div>
                <div class="detail-value"><?= out($slug) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Definition</div>
                <div class="detail-content"><?= nl2br(out($definition)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Source</div>
                <div class="detail-value"><?= out($source) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= out($status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Merged Into Id</div>
                <div class="detail-value"><?= out($merged_into_id) ?></div>
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
