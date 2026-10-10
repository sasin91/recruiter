<?php
/**
 * Matchmaker cards: a batch of a post's applicants, then (when there are
 * more) the link that loads the next batch. Shown by post.php, and alone
 * by Matchmaker::more() for matchmaker.js to append.
 */
$tag_names = ['top' => 'Top match', 'good' => 'Good match', 'medium' => 'Medium match', 'poor' => 'Weak match'];
$verdict_of = fn(array $detail) => (float) $detail['rank'] >= 1 ? 'met' : ((float) $detail['rank'] > 0 ? 'partial' : 'missing');

/** A one-button POST form acting on an application. */
$action_button = function (array $a, string $action, string $label, string $class = 'button small') use ($post, $tab, $tag, $required, $after, $since) {
    return form_open('matchmaker/submit_action/' . (int) $post['id'] . '/' . (int) $a['id'], ['class' => 'inline'])
        . form_hidden('action', $action)
        . form_hidden('tab', $tab)
        . form_hidden('tag', $tag)
        . form_hidden('required', $required ? '1' : '')
        . form_hidden('after', $after)
        . form_hidden('since', (string) $since)
        . form_submit('submit', $label, ['class' => $class])
        . form_close();
};
?>
    <?php foreach ($applications as $a): ?>
        <?php $pct = $a['combined_score'] === null ? null : (int) round($a['combined_score'] * 100); ?>
        <article class="candidate<?= $a['status'] === 'rejected' ? ' rejected' : '' ?>" id="a<?= (int) $a['id'] ?>">
            <div class="candidate-head">
                <?php if ($pct !== null): ?>
                    <div class="score-ring <?= out($a['tag']) ?>" style="--pct: <?= $pct ?>" title="Legacy score <?= $pct ?> of 100"><span><?= $pct ?></span></div>
                <?php else: ?>
                    <div class="score-ring none" title="Not scored yet"><span>–</span></div>
                <?php endif; ?>
                <div class="who">
                    <h2>
                        <?php if ($a['final_rank'] !== null && $a['status'] === 'in_review'): ?><span class="rank">#<?= (int) $a['final_rank'] ?></span><?php endif; ?>
                        <?= out($a['name']) ?>
                        <?php if ($a['is_new']): ?><span class="new-dot">NEW</span><?php endif; ?>
                    </h2>
                    <p class="muted small">
                        <?= $a['current_title'] ? out($a['current_title']) . ' · ' : '' ?>Applied <?= date('j M', (int) $a['submitted_at']) ?>
                        <?php if ($a['tag']): ?> · <span class="tag <?= out($a['tag']) ?>"><?= $tag_names[$a['tag']] ?? out($a['tag']) ?></span><?php endif; ?>
                        <?php if ($a['shortlisted_at']): ?> · <span class="flag">Shortlisted</span><?php endif; ?>
                        <?php if ($a['bookmarked_at']): ?> · <span class="flag">Bookmarked</span><?php endif; ?>
                    </p>
                    <?php if ($a['laya_meets'] !== null): ?>
                        <p class="laya small">
                            Laya: <?= (float) $a['laya_meets'] >= 0.5 ? 'likely meets every requirement' : 'likely misses a requirement' ?>
                            (<?= (int) round($a['laya_meets'] * 100) ?>%), <?= out((string) $a['laya_choice']) ?>.
                            <?php if ((int) $a['needs_human']): ?><strong>Unsure: worth a look.</strong><?php endif; ?>
                        </p>
                    <?php elseif ($laya_on && $a['current']): ?>
                        <p class="laya small warn">Laya didn't answer when this was scored, so it ranks below those Laya answered for. Re-score to try again.</p>
                    <?php endif; ?>
                </div>
            </div>

            <?php $scoring = $a['scoring']; ?>
            <?php if ($scoring && $scoring->is_failed()): ?>
                <p class="small warn">Scoring failed after <?= (int) $scoring->attempts ?> tr<?= (int) $scoring->attempts === 1 ? 'y' : 'ies' ?>: <?= out((string) $scoring->error_message) ?> Re-score to try again.</p>
            <?php elseif ($scoring): ?>
                <p class="small muted"><?= $scoring->is_running() ? 'Being scored now.' : 'Waiting to be scored.' ?><?= $scoring->error_message !== null ? ' The last try failed (' . out($scoring->error_message) . '), so it will try again.' : '' ?></p>
            <?php endif; ?>
            <?php if ($a['current']): ?>
                <?php foreach (['requirements' => 'Requirements', 'skills' => 'Nice to have'] as $group => $heading): ?>
                    <?php if ($groups[$group]): ?>
                        <ul class="chips" aria-label="<?= $heading ?>">
                            <?php foreach ($groups[$group] as $item): ?>
                                <?php $detail = $a['details'][$item['id']] ?? null; $v = $detail ? $verdict_of($detail) : 'missing'; ?>
                                <li class="<?= $v ?><?= $group === 'requirements' ? ' required' : '' ?>" title="<?= out($detail['reason'] ?? '') ?>">
                                    <span class="mark" aria-hidden="true"></span><?= out($item['text']) ?>
                                    <span class="visually-hidden"><?= ['met' => 'met', 'partial' => 'partly met', 'missing' => 'missing'][$v] ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php elseif ($a['score_id'] !== null): ?>
                <p class="muted small">Scored on version <?= (int) $a['score_version'] ?> of the post. Re-score to match it against the requirements as they are now.</p>
            <?php elseif (!$scoring): ?>
                <p class="muted small">Not scored: scoring failed when it came in. Re-score to try again.</p>
            <?php endif; ?>

            <details>
                <summary>CV<?= $a['cover_letter'] ? ' and cover letter' : '' ?></summary>
                <p class="small">
                    <a href="mailto:<?= out($a['email']) ?>"><?= out($a['email']) ?></a><?= $a['phone'] ? ' · ' . out($a['phone']) : '' ?>
                </p>
                <?php if ($a['cover_letter']): ?>
                    <h3>Cover letter</h3>
                    <div class="pitch"><?= out($a['cover_letter']) ?></div>
                <?php endif; ?>
                <h3>CV</h3>
                <div class="pitch cv-text"><?= out((string) $a['raw_text']) ?></div>
                <?php if ($a['current'] && $a['details']): ?>
                    <h3>Why</h3>
                    <ul class="reasons small">
                        <?php foreach (array_merge($groups['requirements'], $groups['skills']) as $item): ?>
                            <?php if ($detail = $a['details'][$item['id']] ?? null): ?>
                                <li><strong><?= out($item['text']) ?>:</strong> <?= out((string) $detail['reason']) ?> <span class="muted">(<?= $detail['stage'] === 'llm' ? 'AI' : ($detail['stage'] === 'none' ? 'not judged' : out($detail['stage'])) ?>)</span></li>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </details>

            <div class="actions-bar card-actions">
                <?php if ($a['status'] === 'in_review'): ?>
                    <?= $a['shortlisted_at'] ? $action_button($a, 'unshortlist', 'Remove from shortlist') : $action_button($a, 'shortlist', 'Shortlist', 'button small primary') ?>
                <?php endif; ?>
                <?php if ($a['status'] !== 'rejected'): ?>
                    <?= $a['bookmarked_at'] ? $action_button($a, 'unbookmark', 'Remove bookmark') : $action_button($a, 'bookmark', 'Bookmark') ?>
                <?php endif; ?>
                <?php if ($a['status'] === 'in_review'): ?>
                    <?= $action_button($a, 'reject', 'Reject') ?>
                <?php elseif ($a['status'] === 'rejected'): ?>
                    <?= $action_button($a, 'unreject', 'Undo reject') ?>
                <?php endif; ?>
                <?php if ($a['status'] === 'in_review' && (!$a['current'] || ($laya_on && $a['laya_meets'] === null))): ?>
                    <?= $action_button($a, 'rescore', 'Re-score') ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
<?php if ($next !== null): ?>
    <a class="button more" href="matchmaker/post/<?= (int) $post['id'] ?>?<?= out($next) ?>" data-more="matchmaker/more/<?= (int) $post['id'] ?>?<?= out($next) ?>">Show more</a>
<?php endif; ?>
