<?php
$title = 'Job scan';
require __DIR__ . '/header.php';
?>
<main class="narrow">
    <h1>Job scan</h1>
    <p class="muted">Save what you're looking for, and the job scan looks for new job posts on the job sites for you. Every post it finds is matched with your CV and saved with your CV matches, and a good match gets a tailored résumé and a job application right away. It runs on your own API key.</p>

    <?= flashdata('<p class="notice">', '</p>') ?>

    <p><a class="button primary" href="job_scan/create">New job search</a></p>

    <?php if (!$searches): ?>
        <p class="muted">You have no job searches yet.</p>
    <?php else: ?>
        <ul class="searches">
            <?php foreach ($searches as $s): ?>
                <li class="search-card">
                    <a href="job_scan/search/<?= $s['id'] ?>"><strong><?= out($s['name']) ?></strong></a>
                    <?php if ($s['chip'] !== null): ?>
                        <span class="chip <?= $s['chip']['class'] ?>"><?= $s['chip']['label'] ?></span>
                    <?php endif; ?>
                    <br>
                    <span class="muted small"><?= out($s['meta']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</main>
</body>
</html>
