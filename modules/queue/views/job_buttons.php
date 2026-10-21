<?php
/**
 * A job row's buttons ($e['buttons'], Queue_job_row::from), for manage.php.
 */
foreach ($e['buttons'] as $i => $button): ?><?= $i ? ' ' : '' ?><?= form_open($button['url'], ['style' => 'display:inline']) . form_submit('submit', $button['label'], ['class' => 'button alt']) . form_close() ?><?php endforeach; ?>
