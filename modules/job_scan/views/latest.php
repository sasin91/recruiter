<?php
/**
 * The first batch of a search's posts for the search page to poll
 * (Job_scan::latest), as posts.php gives it. The section (not a div: Trongate
 * MX unwraps an outer div) tells the page the answer is the list and not,
 * say, the sign-in page.
 */
?>
<section class="posts-latest">
    <?php require __DIR__ . '/posts.php'; ?>
</section>
