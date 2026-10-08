<?php
/**
 * SmartMatch: a post's applicants as cards, in tabs, with filters.
 */
$title = $post['title'] . ': candidates';
require __DIR__ . '/../../company/views/header.php';
$tag_names = ['top' => 'Top match', 'good' => 'Good match', 'medium' => 'Medium match', 'poor' => 'Weak match'];
$verdict_of = fn(array $detail) => (float) $detail['rank'] >= 1 ? 'met' : ((float) $detail['rank'] > 0 ? 'partial' : 'missing');
$list_url = 'smartmatch/post/' . (int) $post['id'];

/** A one-button POST form acting on an application. */
$action_button = function (array $a, string $action, string $label, string $class = 'button small') use ($post, $tab) {
    return form_open('smartmatch/submit_action/' . (int) $post['id'] . '/' . (int) $a['id'], ['class' => 'inline'])
        . form_hidden('action', $action)
        . form_hidden('tab', $tab)
        . form_submit('submit', $label, ['class' => $class])
        . form_close();
};
?>
<main>
    <div class="page-head">
        <div>
            <p class="sub muted small"><a href="company">Job posts</a> ›</p>
            <h1><?= out($post['title']) ?></h1>
            <p class="sub muted small">
                <?= array_sum([$counts['all'], $counts['rejected']]) ?> applicant<?= array_sum([$counts['all'], $counts['rejected']]) === 1 ? '' : 's' ?><?= $new ? ", <strong>$new new</strong> since you last looked" : '' ?>.
                Version <?= (int) $post['version'] ?>.
            </p>
        </div>
        <div class="actions-bar">
            <a class="button" href="smartmatch/csv/<?= (int) $post['id'] ?>">Download CSV</a>
            <a class="button" href="job_posts/review/<?= (int) $post['id'] ?>">Edit post</a>
        </div>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?php if ($unscored): ?>
        <p class="notice"><?= $unscored ?> application<?= $unscored === 1 ? " was" : 's were' ?> scored on an earlier version of the post, or not yet. Use <em>Re-score</em> on the card.</p>
    <?php endif; ?>

    <nav class="tabs" aria-label="Candidates">
        <?php foreach (Smartmatch::TABS as $key => $name): ?>
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
            <?php if ($counts['all'] + $counts['rejected'] === 0): ?>
                <p>No applications yet.</p>
                <?php if ($post['public_token']): ?><p class="small">Share the post's link: <a href="jobs/<?= out($post['public_token']) ?>"><?= out(BASE_URL . 'jobs/' . $post['public_token']) ?></a></p><?php endif; ?>
            <?php else: ?>
                <p>Nobody here<?= $filtered_out ? ' with these filters' : '' ?>.</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="candidates">
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
                    <?php endif; ?>
                </div>
            </div>

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
            <?php else: ?>
                <p class="muted small">Not scored yet.</p>
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
                <?php if (!$a['current'] && $a['status'] === 'in_review'): ?>
                    <?= $action_button($a, 'rescore', 'Re-score') ?>
                <?php endif; ?>
            </div>
        </article>
    <?php endforeach; ?>
    </div>
</main>
</body>
</html>
