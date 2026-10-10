<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Company Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor(BASE_URL.'companies/create/'.$update_id, 'Edit', array('class' => 'button')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Name</div>
                <div class="detail-value"><?= out($name) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Cvr Number</div>
                <div class="detail-value"><?= out($cvr_number) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Company Type Term Id</div>
                <div class="detail-value"><?= out($company_type_term_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Contact Email</div>
                <div class="detail-value"><?= out($contact_email) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Active</div>
                <div class="detail-value"><?= out($active) ?></div>
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
