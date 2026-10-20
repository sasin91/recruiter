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

    <?php if ($last_run): ?>
        <p class="small muted">
            Last scan <?= date('j M H:i', (int) $last_run['started_at']) ?>:
            <?php if ($last_run['finished_at'] === null): ?>
                running…
            <?php elseif ($last_run['error'] !== ''): ?>
                <span class="error-text"><?= out($last_run['error']) ?></span>
            <?php else: ?>
                found <?= (int) $last_run['posts_found'] ?>, <?= (int) $last_run['posts_new'] ?> new.
            <?php endif; ?>
            Scans in the last 30 days: <?= (int) $tokens['scans'] ?>, <?= number_format((int) $tokens['input_tokens'] + (int) $tokens['output_tokens']) ?> tokens.
        </p>
    <?php endif; ?>

    <p class="actions-bar">
        <?php if ((int) $search['active'] && $can_scan_now): ?>
            <?= form_open('job_scan/submit_scan/' . (int) $search['id'], ['class' => 'inline']) . form_submit('scan', 'Scan now', ['class' => 'button primary']) . form_close() ?>
        <?php endif; ?>
        <a class="button" href="job_scan/edit/<?= (int) $search['id'] ?>">Edit</a>
        <?php if ($dismissed): ?>
            <a href="job_scan/search/<?= (int) $search['id'] ?>">Hide dismissed posts</a>
        <?php else: ?>
            <a href="job_scan/search/<?= (int) $search['id'] ?>?dismissed=1">Show dismissed posts</a>
        <?php endif; ?>
    </p>

    <?= form_open('job_scan/submit_add/' . (int) $search['id'], ['class' => 'add-post']) ?>
        <?= form_label('Add a job post by its link', ['for' => 'url']) ?>
        <div class="add-row">
            <?= form_url('url', '', ['id' => 'url', 'required' => true, 'placeholder' => 'https://…']) ?>
            <?= form_submit('add', 'Add and match', ['class' => 'button primary']) ?>
        </div>
    <?= form_close() ?>

    <details class="api-token"<?= $new_token !== null ? ' open' : '' ?>>
        <summary>Post job links from ChatGPT or another tool</summary>
        <p class="small muted">A tool with this search's token can send it job links, which are matched like links added above. For a ChatGPT Action, import the schema from <code><?= out(BASE_URL) ?>job_scan/openapi</code> and set authentication to API key, Bearer, with the token.</p>
        <?php if ($new_token !== null): ?>
            <p class="notice">Copy the token now: it isn't shown again.</p>
            <p><input type="text" class="token" readonly value="<?= out($new_token) ?>" aria-label="Token"></p>
        <?php endif; ?>
        <p class="actions-bar">
            <?= form_open('job_scan/submit_token/' . (int) $search['id'], ['class' => 'inline']) . form_submit('token', $has_token ? 'Make a new token' : 'Make a token', ['class' => 'button']) . form_close() ?>
            <?php if ($has_token): ?>
                <?= form_open('job_scan/submit_token_delete/' . (int) $search['id'], ['class' => 'inline']) . form_submit('token_off', 'Turn off', ['class' => 'button']) . form_close() ?>
                <span class="small muted">A new token replaces the old one.</span>
            <?php endif; ?>
        </p>
    </details>

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
