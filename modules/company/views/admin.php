<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies · Recruiter admin</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="trongate_administrators/manage">Recruiter admin</a>
    <nav><a href="login/logout">Sign out</a></nav>
</header>
<main>
    <h1>Companies</h1>
    <p class="muted">Check the CVR number (for example on datacvr.virk.dk) before verifying. A verified company's job posts can go live.</p>

    <?php if (!$companies): ?>
        <p class="muted">No companies have signed up yet.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list">
                <thead>
                    <tr><th>Company</th><th>CVR</th><th>Contact</th><th>Members</th><th>Signed up</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($companies as $c): ?>
                    <tr>
                        <td><?= out($c['name']) ?></td>
                        <td><?= out($c['cvr_number'] ?? '') ?></td>
                        <td><?= out($c['contact_email'] ?? '') ?></td>
                        <td><?= (int) $c['members'] ?></td>
                        <td><?= date('j M Y', (int) $c['created_at']) ?></td>
                        <td><?= $c['verified_at'] ? 'Verified ' . date('j M Y', (int) $c['verified_at']) : '<strong>Waiting</strong>' ?></td>
                        <td class="actions">
                            <?= form_open('company/submit_verify', ['class' => 'inline']) ?>
                                <?= form_hidden('company_id', (string) $c['id']) ?>
                                <?= form_hidden('verified', $c['verified_at'] ? '0' : '1') ?>
                                <?= form_submit('submit', $c['verified_at'] ? 'Undo' : 'Verify', ['class' => $c['verified_at'] ? 'button small' : 'button small primary']) ?>
                            <?= form_close() ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
