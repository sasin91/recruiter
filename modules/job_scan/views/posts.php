<?php
/**
 * A batch of a search's found posts, with the "Show more" link to the next
 * batch: $search, $posts, $statuses, $next.
 */
$dismiss_button = function (array $p, bool $undo): string {
    return form_open('job_scan/submit_dismiss/' . (int) $p['id'], ['class' => 'inline'])
        . ($undo ? form_hidden('undo', '1') : '')
        . form_submit('dismiss', $undo ? 'Bring back' : 'Dismiss', ['class' => 'button small'])
        . form_close();
};
?>
<?php foreach ($posts as $p): ?>
    <article class="post-card" id="post-<?= (int) $p['id'] ?>">
        <div class="post-head">
            <div>
                <strong><?= out($p['title'] !== '' ? $p['title'] : $p['url']) ?></strong><br>
                <span class="muted small">
                    <?= out($p['company']) ?><?= $p['location'] !== '' ? ' · ' . out($p['location']) : '' ?>
                    · found <?= date('j M H:i', (int) $p['found_at']) ?>
                    · <a href="<?= out($p['url']) ?>" target="_blank" rel="noopener noreferrer">open the post</a>
                </span>
            </div>
            <?php if ($p['status'] === 'matched' && $p['score'] !== null): ?>
                <?php $pct = (int) round((float) $p['score'] * 100); ?>
                <div class="score-ring <?= out((string) $p['tag']) ?>" style="--pct: <?= $pct ?>"><span><?= $pct ?></span><span class="visually-hidden"> of 100, <?= out((string) $p['tag']) ?> match</span></div>
            <?php else: ?>
                <span class="chip status-<?= out($p['status']) ?>"><?= out($statuses[$p['status']] ?? $p['status']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($p['error'] !== '' && in_array($p['status'], ['failed', 'not_a_job'], true)): ?>
            <p class="small error-text"><?= out($p['error']) ?></p>
        <?php endif; ?>
        <div class="actions-bar">
            <?php if ($p['cv_match_id'] !== null): ?>
                <a class="button small primary" href="cv_match/<?= (int) $p['cv_match_id'] ?>">Open the match<?= $p['resume_written_at'] ? ' and tailored résumé' : '' ?></a>
            <?php endif; ?>
            <?= $dismiss_button($p, $p['status'] === 'dismissed') ?>
        </div>
    </article>
<?php endforeach; ?>
<?php if ($next !== null): ?>
    <a class="button more" href="job_scan/search/<?= (int) $search['id'] ?>?<?= out($next) ?>" data-more="job_scan/more/<?= (int) $search['id'] ?>?<?= out($next) ?>">Show more</a>
<?php endif; ?>
