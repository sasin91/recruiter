<?php
/**
 * The saved matches as rows of the page's list, newest first: $matches
 * (Saved_match_row::from). A row's button opens the match (js/app.js).
 */
foreach ($matches as $m): ?>
<li class="history-<?= out($m['tag']) ?>"><button type="button" data-id="<?= $m['id'] ?>"><span class="history-title"><?= out($m['title']) ?></span><span class="history-meta"><?= out($m['about']) ?> · <time datetime="<?= $m['saved_at'] ?>"><?= out($m['saved_on']) ?></time><?= $m['done'] !== '' ? ' · ' . out($m['done']) : '' ?></span></button></li>
<?php endforeach; ?>
