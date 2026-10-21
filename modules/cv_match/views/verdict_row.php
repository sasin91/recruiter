<?php
/**
 * One requirement with its verdict: $row (Verdict_row). A requirement met by
 * any of several lists each as $alternative (verdict_alternative.php).
 */
?>
<li class="item <?= $row['verdict'] ?>">
    <span class="badge <?= $row['verdict'] ?>"><?= $row['verdict'] ?></span>
    <div>
        <strong><?= out($row['text']) ?></strong>
        <div class="why"><?= out($row['reason']) ?><?php if ($row['evidence'] !== ''): ?><q><?= out($row['evidence']) ?></q><?php endif; ?></div>
        <div class="by muted"><?= out($row['decided_by']) ?></div>
        <?php if ($row['alternatives']): ?>
            <ul class="alternatives">
                <?php foreach ($row['alternatives'] as $alternative): ?>
                    <?php require __DIR__ . '/verdict_alternative.php'; ?>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</li>
