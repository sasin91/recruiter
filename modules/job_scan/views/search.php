<?php
$title = $search['name'];
require __DIR__ . '/header.php';
?>
<main>
    <p><a href="job_scan">&larr; Job scan</a></p>
    <h1><?= out($search['name']) ?></h1>
    <p class="muted">
        <?= out($search['keywords']) ?><?= $search['location'] !== '' ? ' · ' . out($search['location']) : '' ?><?= (int) $search['remote_ok'] ? ' · remote too' : '' ?>
        <?php if ($search['sites']): ?> · on <?= out(implode(', ', $search['sites'])) ?><?php endif; ?>
        · <?= out($scan_every[(int) $search['scan_every_hours']] ?? $search['scan_every_hours'] . ' h') ?>
        · tailors from <?= (int) $search['tailor_from_score'] ?>
        · <?= $search['last_scanned_at'] ? 'last scanned ' . date('j M Y H:i', (int) $search['last_scanned_at']) : 'not scanned yet' ?>
    </p>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <?php if (!(int) $search['active']): ?>
        <p class="notice">This search is off, so it isn't scanned. Turn it on under Edit.</p>
    <?php elseif ($search['paused_reason'] !== ''): ?>
        <p class="notice"><?= out($search['paused_reason']) ?></p>
    <?php endif; ?>

    <p class="actions-bar">
        <a class="button" href="job_scan/edit/<?= (int) $search['id'] ?>">Edit</a>
        <?php if ($dismissed): ?>
            <a href="job_scan/search/<?= (int) $search['id'] ?>">Hide dismissed posts</a>
        <?php else: ?>
            <a href="job_scan/search/<?= (int) $search['id'] ?>?dismissed=1">Show dismissed posts</a>
        <?php endif; ?>
    </p>

    <?php if (!$posts && $after === ''): ?>
        <p class="muted">No job posts found yet.</p>
    <?php endif; ?>
    <div class="posts">
        <?php require __DIR__ . '/posts.php'; ?>
    </div>
</main>
<script type="module" src="job_scan_module/js/job_scan.js"></script>
</body>
</html>
