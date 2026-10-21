<?php
$title = 'My applications';
require __DIR__ . '/header.php';
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
                            <a href="<?= out($a['post_url']) ?>"><?= out($a['title']) ?></a><br>
                            <span class="muted small"><?= out($a['company_name']) ?></span>
                        </td>
                        <td><span class="status <?= $a['status_class'] ?>"><?= out($a['status_name']) ?></span></td>
                        <td><?= out($a['sent']) ?></td>
                        <td class="actions">
                            <?php if ($a['can_withdraw']): ?>
                                <?= form_open('applications/submit_withdraw/' . $a['id'], ['class' => 'inline', 'onsubmit' => "return confirm('Withdraw this application? The company will no longer see it.')"]) ?>
                                    <?= form_submit('submit', 'Withdraw', ['class' => 'button small']) ?>
                                <?= form_close() ?>
                            <?php elseif ($a['apply_again_url'] !== null): ?>
                                <a class="button small" href="<?= out($a['apply_again_url']) ?>">Apply again</a>
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
