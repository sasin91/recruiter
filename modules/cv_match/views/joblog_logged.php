<?php
/**
 * Whether the candidate has entered the match in their Jobnet joblog, and
 * when, with the day applied for the date field: $logged (Joblog_panel::logged). Part of views/joblog_panel.php, and
 * joblog_status's answer when it is saved.
 */
?>
<section class="joblog-section joblog-saved" data-applied-on="<?= out($logged['applied_on']) ?>">
    <h5>In your Jobnet joblog</h5>
    <label class="joblog-logged"><input type="checkbox"<?= $logged['checked'] ? ' checked' : '' ?>> <?= out(Joblog_panel::LOGGED_LABEL) ?></label>
    <span class="muted" aria-live="polite"><?php if ($logged['at'] !== ''): ?><?= out(Joblog_panel::LOGGED_ON) ?> <time datetime="<?= $logged['at'] ?>"><?= $logged['at'] ?></time>.<?php endif; ?></span>
</section>
