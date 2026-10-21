<?php
/**
 * Matchmaker cards: a batch of a post's applicants (Applicant_card::from),
 * then (when there are more) the link that loads the next batch. Shown by
 * post.php, and alone by Matchmaker::more() for matchmaker.js to append.
 */
?>
    <?php foreach ($applications as $a): ?>
        <article class="candidate<?= $a['rejected'] ? ' rejected' : '' ?>" id="a<?= $a['id'] ?>">
            <div class="candidate-head">
                <?php if ($a['score'] !== null): ?>
                    <div class="score-ring <?= out($a['score']['tag']) ?>" style="--pct: <?= $a['score']['percent'] ?>" title="Legacy score <?= $a['score']['percent'] ?> of 100"><span><?= $a['score']['percent'] ?></span></div>
                <?php else: ?>
                    <div class="score-ring none" title="Not scored yet"><span>–</span></div>
                <?php endif; ?>
                <div class="who">
                    <h2>
                        <?php if ($a['rank'] !== null): ?><span class="rank">#<?= $a['rank'] ?></span><?php endif; ?>
                        <?= out($a['name']) ?>
                        <?php if ($a['is_new']): ?><span class="new-dot">NEW</span><?php endif; ?>
                    </h2>
                    <p class="muted small">
                        <?= out($a['meta']) ?>
                        <?php if ($a['tag'] !== ''): ?> · <span class="tag <?= out($a['tag']) ?>"><?= out($a['tag_name']) ?></span><?php endif; ?>
                        <?php foreach ($a['flags'] as $flag): ?> · <span class="flag"><?= out($flag) ?></span><?php endforeach; ?>
                    </p>
                    <?php if ($a['laya'] !== null): ?>
                        <p class="laya small">
                            <?= out($a['laya']['text']) ?>
                            <?php if ($a['laya']['unsure']): ?><strong>Unsure: worth a look.</strong><?php endif; ?>
                        </p>
                    <?php elseif ($a['laya_missing']): ?>
                        <p class="laya small warn">Laya didn't answer when this was scored, so it ranks below those Laya answered for. Re-score to try again.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($a['scoring'] !== null): ?>
                <p class="small <?= $a['scoring']['warn'] ? 'warn' : 'muted' ?>"><?= out($a['scoring']['text']) ?></p>
            <?php endif; ?>
            <?php foreach ($a['chips'] as $group): ?>
                <ul class="chips" aria-label="<?= out($group['heading']) ?>">
                    <?php foreach ($group['chips'] as $chip): ?>
                        <li class="<?= $chip['verdict'] ?><?= $group['required'] ? ' required' : '' ?>" title="<?= out($chip['reason']) ?>">
                            <span class="mark" aria-hidden="true"></span><?= out($chip['text']) ?>
                            <span class="visually-hidden"><?= out($chip['verdict_name']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
            <?php if ($a['not_current'] !== ''): ?>
                <p class="muted small"><?= out($a['not_current']) ?></p>
            <?php endif; ?>

            <details>
                <summary><?= out($a['cv']['summary']) ?></summary>
                <p class="small">
                    <a href="mailto:<?= out($a['cv']['email']) ?>"><?= out($a['cv']['email']) ?></a><?php if ($a['cv']['phone'] !== ''): ?> · <?= out($a['cv']['phone']) ?><?php endif; ?>
                </p>
                <?php if ($a['cv']['cover_letter'] !== ''): ?>
                    <h3>Cover letter</h3>
                    <div class="pitch"><?= out($a['cv']['cover_letter']) ?></div>
                <?php endif; ?>
                <h3>CV</h3>
                <div class="pitch cv-text"><?= out($a['cv']['text']) ?></div>
                <?php if ($a['cv']['reasons']): ?>
                    <h3>Why</h3>
                    <ul class="reasons small">
                        <?php foreach ($a['cv']['reasons'] as $reason): ?>
                            <li><strong><?= out($reason['item']) ?>:</strong> <?= out($reason['reason']) ?> <span class="muted">(<?= out($reason['judged_by']) ?>)</span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </details>

            <div class="actions-bar card-actions">
                <?php foreach ($a['buttons'] as $button): ?>
                    <?= form_open($button['url'], ['class' => 'inline']) ?><?php foreach ($button['fields'] as $name => $value): ?><?= form_hidden($name, $value) ?><?php endforeach; ?><?= form_submit('submit', $button['label'], ['class' => $button['class']]) ?><?= form_close() ?>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php if ($next !== null): ?>
    <a class="button more" href="matchmaker/post/<?= (int) $post['id'] ?>?<?= out($next) ?>" data-more="matchmaker/more/<?= (int) $post['id'] ?>?<?= out($next) ?>">Show more</a>
<?php endif; ?>
