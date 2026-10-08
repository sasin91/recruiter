<?php
$title = 'My applications';
require __DIR__ . '/header.php';
$status_names = ['in_review' => 'Sent', 'rejected' => 'Not this time', 'hired' => 'Hired', 'withdrawn' => 'Withdrawn'];
?>
<main>
    <div class="page-head">
        <h1>My applications</h1>
    </div>
    <?= flashdata('<p class="notice">', '</p>') ?>

    <?php if (!$applications): ?>
        <div class="empty"><p>You haven't applied for anything yet.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="list post-list">
                <thead><tr><th>Job</th><th>Status</th><th>Sent</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($applications as $a): ?>
                    <tr>
                        <td class="title">
                            <a href="jobs/<?= out($a['public_token']) ?>"><?= out($a['title']) ?></a><br>
                            <span class="muted small"><?= out($a['company_name']) ?></span>
                        </td>
                        <td><span class="status <?= $a['status'] === 'in_review' ? 'active' : 'closed' ?>"><?= $status_names[$a['status']] ?? out($a['status']) ?></span></td>
                        <td><?= date('j M Y', (int) $a['submitted_at']) ?></td>
                        <td class="actions">
                            <?php if ($a['status'] === 'in_review'): ?>
                                <?= form_open('applications/submit_withdraw/' . (int) $a['id'], ['class' => 'inline', 'onsubmit' => "return confirm('Withdraw this application? The company will no longer see it.')"]) ?>
                                    <?= form_submit('submit', 'Withdraw', ['class' => 'button small']) ?>
                                <?= form_close() ?>
                            <?php elseif ($a['status'] === 'withdrawn' && $a['post_status'] === 'active'): ?>
                                <a class="button small" href="jobs/<?= out($a['public_token']) ?>/apply">Apply again</a>
                            <?php endif; ?>
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
