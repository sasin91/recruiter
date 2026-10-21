<?php
/**
 * The "add the extension" banner: $links, the store links by browser.
 * Hidden until js/banner.js decides this browser should see it.
 */
?>
<link rel="stylesheet" href="joblog_extension_module/css/banner.css">
<aside id="joblog-extension-banner" class="joblog-extension-banner" hidden
    <?php foreach ($links as $browser => $link): ?> data-<?= $browser ?>="<?= out($link) ?>"<?php endforeach; ?>>
    <p><strong>Log your applications on Jobnet in one click.</strong> The Recruiter extension fills in Jobnet's "Opret joblog" from your saved match.</p>
    <a class="add" href="joblog_extension" target="_blank" rel="noopener">Add the extension</a>
    <a href="joblog_extension">How it works</a>
    <button type="button" class="close" aria-label="Don't show this again">×</button>
</aside>
<script type="module" src="joblog_extension_module/js/banner.js"></script>
