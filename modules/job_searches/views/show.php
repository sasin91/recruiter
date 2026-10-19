<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Job Search Details
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
                <div class="detail-label">Name</div>
                <div class="detail-value"><?= out($name) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Keywords</div>
                <div class="detail-value"><?= out($keywords) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Location</div>
                <div class="detail-value"><?= out($location) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Remote Ok</div>
                <div class="detail-value"><?= out($remote_ok) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">CV Name</div>
                <div class="detail-value"><?= out($cv_name) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Tailor From Score</div>
                <div class="detail-value"><?= out($tailor_from_score) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Scan Every Hours</div>
                <div class="detail-value"><?= out($scan_every_hours) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Active</div>
                <div class="detail-value"><?= out($active) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Paused Reason</div>
                <div class="detail-value"><?= out($paused_reason) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last Scanned At</div>
                <div class="detail-value"><?= out($last_scanned_at) ?></div>
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
