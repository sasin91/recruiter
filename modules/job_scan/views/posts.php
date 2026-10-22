<?php
/**
 * A batch of a search's found posts, with the "Show more" link to the next
 * batch: $search, $posts (Found_post_card::from), $next.
 */
?>
<?php foreach ($posts as $p): ?>
    <article class="post-card" id="post-<?= $p['id'] ?>">
        <div class="post-head">
            <div>
                <strong><?= out($p['heading']) ?></strong><br>
                <span class="muted small">
                    <?= out($p['meta']) ?>
                    · <a href="<?= out($p['url']) ?>" target="_blank" rel="noopener noreferrer">open the post</a>
                </span>
            </div>
            <?php if ($p['score'] !== null): ?>
                <div class="score-ring <?= out($p['score']['tag']) ?>" style="--pct: <?= $p['score']['percent'] ?>"><span><?= $p['score']['percent'] ?></span><span class="visually-hidden"> of 100, <?= out($p['score']['tag_name']) ?></span></div>
            <?php else: ?>
                <span class="chip status-<?= out($p['status']) ?>"><?= out($p['status_name']) ?></span>
            <?php endif; ?>
        </div>
        <?php if ($p['disqualifier'] !== ''): ?>
            <p class="small disqualifier-text"><?= out($p['disqualifier']) ?></p>
        <?php endif; ?>
        <?php if ($p['error'] !== ''): ?>
            <p class="small error-text"><?= out($p['error']) ?></p>
        <?php endif; ?>
        <div class="actions-bar">
            <?php if ($p['match_link'] !== null): ?>
                <a class="button small primary" href="<?= out($p['match_link']['url']) ?>"><?= out($p['match_link']['label']) ?></a>
            <?php endif; ?>
            <?php if ($p['can_retry']): ?>
                <?= form_open('job_scan/submit_retry/' . $p['id'], ['class' => 'inline']) . form_submit('retry', 'Try again', ['class' => 'button small']) . form_close() ?>
            <?php endif; ?>
            <?= form_open('job_scan/submit_dismiss/' . $p['id'], ['class' => 'inline']) ?>
                <?php foreach ($p['dismiss']['fields'] as $name => $value): ?><?= form_hidden($name, $value) ?><?php endforeach; ?>
                <?= form_submit('dismiss', $p['dismiss']['label'], ['class' => 'button small']) ?>
            <?= form_close() ?>
        </div>
    </article>
<?php endforeach; ?>
<?php if ($next !== null): ?>
    <a class="button more" href="job_scan/search/<?= (int) $search['id'] ?>?<?= out($next) ?>" data-more="job_scan/more/<?= (int) $search['id'] ?>?<?= out($next) ?>">Show more</a>
<?php endif; ?>
