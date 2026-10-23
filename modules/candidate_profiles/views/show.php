<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Candidate Profile Details
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
                <div class="detail-label">Title</div>
                <div class="detail-value"><?= out($title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Location</div>
                <div class="detail-value"><?= out($location) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">About</div>
                <div class="detail-value"><?= out($about) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Filled From CV Name</div>
                <div class="detail-value"><?= out($filled_from_cv_name) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Filled From CV At</div>
                <div class="detail-value"><?= out($filled_from_cv_at) ?></div>
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
