<?php
/**
 * The saved matches as rows of the page's list, newest first: $matches
 * (Cv_match_model::recent). A row's button opens the match (js/app.js).
 */
foreach ($matches as $m):
    $written = array_filter([
        (int) $m['has_application'] ? 'application' : '',
        (int) $m['has_resume'] ? 'résumé' : '',
        $m['jobnet_logged_at'] !== null ? 'on Jobnet' : '',
    ]);
    $meta = array_merge(
        [round((float) $m['score'] * 100) . '% ' . $m['tag']],
        str_ends_with((string) $m['cv_name'], '(tailored résumé)') ? ['tailored résumé'] : [],
        [date('j M Y', (int) $m['created_at'])],
        $written
    );
    $title = implode(' · ', array_filter([(string) $m['job_title'], (string) $m['company']], fn($part) => $part !== ''));
?>
<li class="history-<?= out((string) $m['tag']) ?>"><button type="button" data-id="<?= (int) $m['id'] ?>"><span class="history-title"><?= out($title !== '' ? $title : 'Untitled') ?></span><span class="history-meta"><?= out(implode(' · ', $meta)) ?></span></button></li>
<?php endforeach; ?>
