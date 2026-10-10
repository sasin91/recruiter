<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Term Label Details
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
                <div class="detail-label">Lang</div>
                <div class="detail-value"><?= out($lang) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Label</div>
                <div class="detail-value"><?= out($label) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Normalised</div>
                <div class="detail-value"><?= out($normalised) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Is Preferred</div>
                <div class="detail-value"><?= out($is_preferred) ?></div>
            </div>
        </div>
    </div>
</div>
