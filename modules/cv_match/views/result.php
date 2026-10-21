<?php
/**
 * A match's result, put in #result by js/app.js: $result (Match_result).
 * The score ring fills and the number counts up when it is shown; the
 * filter buttons show only the missing, partial or met requirements.
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
    <?php if ($result['apply']) {
        $apply = $result['apply'];
        require __DIR__ . '/apply_notice.php';
    } ?>
    <?php if ($result['filters']): ?>
        <div class="filter" role="group" aria-label="Show requirements">
            <?php foreach ($result['filters'] as $filter): ?>
                <button type="button" class="filter-<?= $filter['key'] ?>" data-show="<?= $filter['key'] ?>" aria-pressed="<?= $filter['key'] === 'all' ? 'true' : 'false' ?>"><?= out($filter['label']) ?><span class="count"><?= $filter['count'] ?></span></button>
            <?php endforeach; ?>
        </div>
        <div class="verdicts">
            <?php foreach ($result['sections'] as $section): ?>
                <section class="group">
                    <h3><?= out($section['heading']) ?><?php if ($section['note'] !== ''): ?> <span class="muted"><?= out($section['note']) ?></span><?php endif; ?></h3>
                    <ul>
                        <?php foreach ($section['rows'] as $row): ?>
                            <?php require __DIR__ . '/verdict_row.php'; ?>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($result['soft_skills']): ?>
        <div id="soft-skills">
            <h3>Soft skills <span class="muted">not scored</span></h3>
            <?php foreach ($result['soft_skills'] as $skill): ?>
                <span class="chip"><?= out($skill) ?></span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
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
        <template class="cv-found" data-status="<?= out($result['cv_found']['status']) ?>"><?php foreach ($result['cv_found']['terms'] as $term): ?><span class="chip"><?= out($term) ?></span><?php endforeach; ?><?php if ($result['cv_found']['more'] !== ''): ?><span class="muted"><?= out($result['cv_found']['more']) ?></span><?php endif; ?></template>
    <?php endif; ?>
</div>
