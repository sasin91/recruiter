<?php
/**
 * The body of a match's result, under its score: what the post asks of
 * applicants, the requirements with their verdicts and a filter by
 * verdict, and the soft skills. $result (Match_result). Printed by
 * result.php and batch_row.php.
 */
?>
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
    <div class="soft-skills">
        <h3>Soft skills <span class="muted">not scored</span></h3>
        <?php foreach ($result['soft_skills'] as $skill): ?>
            <span class="chip"><?= out($skill) ?></span>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
