<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Candidate Profile Entry Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Candidate Id</div>
                <div class="detail-value"><?= out($candidate_id) ?></div>
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
                <div class="detail-label">URL</div>
                <div class="detail-value"><?= out($url) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Started On</div>
                <div class="detail-value"><?= out($started_on) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Ended On</div>
                <div class="detail-value"><?= out($ended_on) ?></div>
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
