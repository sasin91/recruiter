<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Tailored Résumé Entry Details
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
                <div class="detail-label">Section</div>
                <div class="detail-value"><?= out($section) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Title</div>
                <div class="detail-value"><?= out($title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Organisation</div>
                <div class="detail-value"><?= out($organisation) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Location</div>
                <div class="detail-value"><?= out($location) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Starts</div>
                <div class="detail-value"><?= out($starts) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Ends</div>
                <div class="detail-value"><?= out($ends) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Summary</div>
                <div class="detail-value"><?= out($summary) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Note</div>
                <div class="detail-value"><?= out($note) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Sort Order</div>
                <div class="detail-value"><?= out($sort_order) ?></div>
            </div>
        </div>
    </div>
</div>
