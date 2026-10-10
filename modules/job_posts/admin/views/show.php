<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Job Post Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor('job_posts-admin/delete_conf/'.$update_id, 'Delete',  array('class' => 'button danger')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Company Id</div>
                <div class="detail-value"><?= out($company_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created By</div>
                <div class="detail-value"><?= out($created_by) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Title</div>
                <div class="detail-value"><?= out($title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Level</div>
                <div class="detail-value"><?= out($level) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Workplace Flexibility</div>
                <div class="detail-value"><?= out($workplace_flexibility) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Work Hours</div>
                <div class="detail-value"><?= out($work_hours) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Postal Code</div>
                <div class="detail-value"><?= out($postal_code) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Country Code</div>
                <div class="detail-value"><?= out($country_code) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Language</div>
                <div class="detail-value"><?= out($language) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= out($status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Version</div>
                <div class="detail-value"><?= out($version) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Raw Text</div>
                <div class="detail-content"><?= nl2br(out($raw_text)) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Pitch</div>
                <div class="detail-content"><?= nl2br(out($pitch)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Extractor</div>
                <div class="detail-value"><?= out($extractor) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">External Id</div>
                <div class="detail-value"><?= out($external_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Public Token</div>
                <div class="detail-value"><?= out($public_token) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Accepts Suggested</div>
                <div class="detail-value"><?= out($accepts_suggested) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Max Suggested Applications</div>
                <div class="detail-value"><?= out($max_suggested_applications) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Published At</div>
                <div class="detail-value"><?= out($published_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Paused At</div>
                <div class="detail-value"><?= out($paused_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Closed At</div>
                <div class="detail-value"><?= out($closed_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Archived At</div>
                <div class="detail-value"><?= out($archived_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Closes At</div>
                <div class="detail-value"><?= out($closes_at) ?></div>
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
