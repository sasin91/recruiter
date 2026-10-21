--TEST--
saved matches print as links to their own page, ending with Show more
--FILE--
<?php
function out($value) { return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8'); }
$matches = [['id' => 7, 'tag' => 'good', 'title' => 'Backend · Fragt', 'about' => '81% good', 'saved_at' => '2026-10-21T23:30:00Z', 'saved_on' => '21 Oct 2026', 'done' => '']];
$next = 'after=0.8140_1792620000_7';
require __DIR__ . '/../views/history.php';
$next = null;
require __DIR__ . '/../views/history.php';
?>
--EXPECT--
<li class="history-good" id="saved-match-7"><a href="cv_match/7" data-id="7"><span class="history-title">Backend · Fragt</span><span class="history-meta">81% good · <time datetime="2026-10-21T23:30:00Z">21 Oct 2026</time></span></a></li>
<li class="history-more"><a class="more" href="cv_match?after=0.8140_1792620000_7" data-more="cv_match/history_more?after=0.8140_1792620000_7">Show more</a></li>
<li class="history-good" id="saved-match-7"><a href="cv_match/7" data-id="7"><span class="history-title">Backend · Fragt</span><span class="history-meta">81% good · <time datetime="2026-10-21T23:30:00Z">21 Oct 2026</time></span></a></li>
