<?php
/**
 * A match as a row of "Jobs ranked by match": $row (Batch_row) over $result
 * (Match_result), with the application and résumé boxes of a saved match.
 * js/app.js ranks the rows by data-score. The free match carries what it
 * found in the CV (.cv-found) for the CV panel.
 */
?>
<li class="batch-row" data-score="<?= $row['percent'] ?>" data-saved-id="<?= $result['saved_id'] ?? '' ?>">
    <details>
        <summary class="batch-summary">
            <span class="batch-score <?= $row['tag'] ?>"><?= $row['percent'] ?>%</span>
            <div class="batch-head">
                <strong><?= out($row['heading']) ?></strong>
                <div class="muted">
                    <span class="tag <?= $row['tag'] ?>"><?= out($row['tag']) ?></span><?php if ($row['host'] !== ''): ?> · <?= out($row['host']) ?><?php endif; ?><?php if ($row['matched_at'] !== ''): ?> · matched before, <time datetime="<?= $row['matched_at'] ?>"><?= $row['matched_at'] ?></time><?php endif; ?><?php if ($row['apply_chip']): ?> · <span class="apply-chip<?= $row['apply_chip']['trap'] ? ' trap' : '' ?>"><?= out($row['apply_chip']['label']) ?></span><?php endif; ?>
                </div>
                <span class="batch-bar <?= $row['tag'] ?>" style="--value: <?= $row['percent'] ?>"></span>
            </div>
            <span class="chevron"></span>
        </summary>
        <div class="muted"><?= out($row['line']) ?></div>
        <?php require __DIR__ . '/result_body.php'; ?>
        <?php if ($result['documents']): ?>
            <div class="batch-documents"><?php $documents = $result['documents']; require __DIR__ . '/documents.php'; ?></div>
        <?php endif; ?>
    </details>
    <?php if (isset($result['cv_found'])): ?>
        <?php require __DIR__ . '/cv_found.php'; ?>
    <?php endif; ?>
</li>
