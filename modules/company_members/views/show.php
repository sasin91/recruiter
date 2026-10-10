<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Company Member Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
            <?= anchor(BASE_URL.'company_members/create/'.$update_id, 'Edit', array('class' => 'button')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Company Id</div>
                <div class="detail-value"><?= out($company_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Trongate User Id</div>
                <div class="detail-value"><?= out($trongate_user_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Name</div>
                <div class="detail-value"><?= out($name) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?= out($email) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Role</div>
                <div class="detail-value"><?= out($role) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Active</div>
                <div class="detail-value"><?= out($active) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Invited At</div>
                <div class="detail-value"><?= out($invited_at) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Num Logins</div>
                <div class="detail-value"><?= out($num_logins) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Last Login</div>
                <div class="detail-value"><?= out($last_login) ?></div>
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
