<?php
/** One of the alternatives of a requirement (verdict_row.php): $alternative (Verdict_row). */
?>
<li class="item <?= $alternative['verdict'] ?>">
    <span class="badge <?= $alternative['verdict'] ?>"><?= $alternative['verdict'] ?></span>
    <div>
        <strong><?= out($alternative['text']) ?></strong>
        <div class="why"><?= out($alternative['reason']) ?><?php if ($alternative['evidence'] !== ''): ?><q><?= out($alternative['evidence']) ?></q><?php endif; ?></div>
        <div class="by muted"><?= out($alternative['decided_by']) ?></div>
    </div>
</li>
