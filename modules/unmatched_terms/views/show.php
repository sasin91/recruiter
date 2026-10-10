<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Unmatched Term Details
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
                <div class="detail-label">Lang</div>
                <div class="detail-value"><?= out($lang) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Normalised</div>
                <div class="detail-value"><?= out($normalised) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Example Raw Text</div>
                <div class="detail-value"><?= out($example_raw_text) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Occurrences</div>
                <div class="detail-value"><?= out($occurrences) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Suggested Term Id</div>
                <div class="detail-value"><?= out($suggested_term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Suggested Similarity</div>
                <div class="detail-value"><?= out($suggested_similarity) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Status</div>
                <div class="detail-value"><?= out($status) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Resolved Term Id</div>
                <div class="detail-value"><?= out($resolved_term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Resolved By</div>
                <div class="detail-value"><?= out($resolved_by) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Resolved At</div>
                <div class="detail-value"><?= out($resolved_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Created At</div>
                <div class="detail-value"><?= out($created_at) ?></div>
            </div>
        </div>
    </div>
</div>
