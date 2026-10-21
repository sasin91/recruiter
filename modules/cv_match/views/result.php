<?php
/**
 * A match's result, put in #result by js/app.js: $result (Match_result).
 * The score ring fills and the number counts up when it is shown; the
 * filter buttons show only the missing, partial or met requirements
 * (result_body.php, which the batch rows print too).
 * A saved match carries its post (.match-job) for the job box, and the free
 * match what it found in the CV (.cv-found) for the CV panel.
 */
?>
<div class="match-result" data-saved-id="<?= $result['saved_id'] ?? '' ?>">
    <div class="summary">
        <div class="score-ring <?= $result['score']['tag'] ?>" id="score-ring" style="--value: <?= $result['score']['percent'] ?>" data-percent="<?= $result['score']['percent'] ?>">
            <svg viewBox="0 0 120 120" aria-hidden="true">
                <circle class="track" cx="60" cy="60" r="52"></circle>
                <circle class="meter" cx="60" cy="60" r="52"></circle>
            </svg>
            <div class="score"><span id="score-value"><?= $result['score']['percent'] ?></span><small>%</small></div>
        </div>
        <div>
            <div id="score-tag" class="tag <?= $result['score']['tag'] ?>"><?= out($result['score']['tag']) ?></div>
            <div id="job-heading"><?= out($result['heading']) ?></div>
            <div id="score-parts" class="muted"><?= out($result['parts']) ?><?php if ($result['saved_at'] !== ''): ?> · saved <time datetime="<?= $result['saved_at'] ?>" data-format="datetime"><?= $result['saved_at'] ?></time><?php endif; ?></div>
        </div>
    </div>
    <?php require __DIR__ . '/result_body.php'; ?>
    <?php if ($result['documents']): ?>
        <section class="panel" id="application-panel">
            <h3>Application and résumé</h3>
            <div id="documents"><?php $documents = $result['documents']; require __DIR__ . '/documents.php'; ?></div>
        </section>
    <?php endif; ?>
    <?php if ($result['laya']['text'] !== ''): ?>
        <p id="laya-answer"<?= $result['laya']['failed'] ? ' class="muted"' : '' ?>><?= out($result['laya']['text']) ?></p>
    <?php endif; ?>
    <?php if ($result['laya_input'] !== ''): ?>
        <div id="laya-block">
            <h3>For Laya</h3>
            <p class="muted">The job post and CV in English with a summary of the requirement verdicts: the input Laya's English checkpoint ranked well in the bake-off.</p>
            <textarea id="laya" rows="6" readonly><?= out($result['laya_input']) ?></textarea>
        </div>
    <?php endif; ?>
    <?php if ($result['job_text'] !== ''): ?>
        <template class="match-job" data-url="<?= out($result['job_url']) ?>"><?= out($result['job_text']) ?></template>
    <?php endif; ?>
    <?php if (isset($result['cv_found'])): ?>
        <?php require __DIR__ . '/cv_found.php'; ?>
    <?php endif; ?>
</div>
