<?php
/**
 * The first batch of saved matches for the page to poll
 * (Cv_match::history_list), as history.php gives it. The section (not a
 * div: Trongate MX unwraps an outer div) tells the page the answer is the
 * list.
 */
?>
<section class="history-latest"><ul><?php require __DIR__ . '/history.php'; ?></ul></section>
