<?php
/**
 * A batch of the saved matches as rows of the page's list, best first:
 * $matches (Saved_match_row::from), and $next, the query of the batch after
 * these, and $list_query, the list asked for (?list=). A row is a link to
 * the match's own page, keeping the list, so it opens in a new tab too; a
 * plain click opens it in place (js/app.js). The last row is "Show more",
 * loaded when it scrolls into view.
 */
foreach ($matches as $m): ?>
<li class="history-<?= out($m['tag']) ?>" id="saved-match-<?= $m['id'] ?>"><a href="cv_match/<?= $m['id'] ?><?= out($list_query) ?>" data-id="<?= $m['id'] ?>"><span class="history-title"><?= out($m['title']) ?></span><span class="history-meta"><?= out($m['about']) ?> · <time datetime="<?= $m['saved_at'] ?>"><?= out($m['saved_on']) ?></time><?= $m['done'] !== '' ? ' · ' . out($m['done']) : '' ?></span></a></li>
<?php endforeach; ?>
<?php if ($next !== null): ?>
<li class="history-more"><a class="more" href="cv_match?<?= out($next) ?>" data-more="cv_match/history_more?<?= out($next) ?>">Show more</a></li>
<?php endif; ?>
