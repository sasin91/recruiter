<?php
/**
 * The tailored résumé's score next to the CV's: $comparison
 * (Resume_comparison). cv_match/resume_comparison answers with it; js/app.js
 * puts it in the résumé box's .compare line.
 */
?>
<?= out($comparison['line']) ?> <?= out(Resume_comparison::SAVED) ?> <button type="button" class="link-button open-saved" data-id="<?= $comparison['open_id'] ?>"><?= out(Resume_comparison::OPEN) ?></button>
