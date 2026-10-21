<?php
/**
 * Matchmaker: a post's applicants as cards, in tabs, with filters.
 */
$title = $post['title'] . ': candidates';
require __DIR__ . '/../../company/views/header.php';
?>
<main>
    <div class="page-head">
        <div>
            <p class="sub muted small"><a href="company">Job posts</a> ›</p>
            <h1><?= out($post['title']) ?></h1>
            <p class="sub muted small">
                <?= $head['applicants'] ?><?php if ($head['new']): ?>, <strong><?= $head['new'] ?> new</strong> since you last looked<?php endif; ?>.
                Version <?= (int) $post['version'] ?>.
            </p>
        </div>
        <div class="actions-bar">
            <a class="button" href="matchmaker/csv/<?= (int) $post['id'] ?>">Download CSV</a>
            <a class="button" href="job_posts/review/<?= (int) $post['id'] ?>">Edit post</a>
        </div>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?php if ($head['unscored'] !== ''): ?>
        <p class="notice"><?= out($head['unscored']) ?> scored on this version of the post: still being scored, scored on an earlier version, or scoring failed. The card says which; use <em>Re-score</em> there.</p>
    <?php endif; ?>
    <?php if ($head['no_laya'] !== null): ?>
        <p class="notice">Laya didn't answer when <?= $head['no_laya']['were'] ?> scored, so <?= $head['no_laya']['rank'] ?> below those it answered for. Use <em>Re-score</em> on the card.</p>
    <?php endif; ?>

    <nav class="tabs" aria-label="Candidates">
        <?php foreach (Matchmaker::TABS as $key => $name): ?>
            <a href="<?= $list_url ?>?tab=<?= $key ?>" class="<?= $tab === $key ? 'current' : '' ?>"<?= $tab === $key ? ' aria-current="page"' : '' ?>><?= $name ?><span class="count"><?= $counts[$key] ?></span></a>
        <?php endforeach; ?>
    </nav>

    <form class="filters" method="get" action="<?= $list_url ?>">
        <input type="hidden" name="tab" value="<?= out($tab) ?>">
        <label>Match
            <select name="tag" onchange="this.form.submit()">
                <option value="">Any</option>
                <?php foreach ($tag_names as $key => $name): ?>
                    <option value="<?= $key ?>"<?= $tag === $key ? ' selected' : '' ?>><?= $name ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="check-label"><input type="checkbox" name="required" value="1"<?= $required ? ' checked' : '' ?> onchange="this.form.submit()"> Meets every requirement</label>
        <noscript><button type="submit" class="button small">Filter</button></noscript>
        <?php if ($filtered_out): ?><span class="muted small"><?= $filtered_out ?> hidden by the filter</span><?php endif; ?>
    </form>

    <?php if (!$applications): ?>
        <div class="empty">
            <?php if (!$head['has_applicants']): ?>
                <p>No applications yet.</p>
                <?php if ($post['public_token']): ?><p class="small">Share the post's link: <a href="jobs/<?= out($post['public_token']) ?>"><?= out(BASE_URL . 'jobs/' . $post['public_token']) ?></a></p><?php endif; ?>
            <?php else: ?>
                <p>Nobody here<?= $filtered_out ? ' with these filters' : '' ?>.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($after !== ''): ?>
        <p class="small"><a href="<?= $list_url ?>?<?= out($query) ?>">Back to the top of the list</a></p>
    <?php endif; ?>
    <div class="candidates">
    <?php require __DIR__ . '/cards.php'; ?>
    </div>

</main>
<script type="module" src="matchmaker_module/js/matchmaker.js"></script>
</body>
</html>
