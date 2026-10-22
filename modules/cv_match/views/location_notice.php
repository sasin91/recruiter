<?php
/**
 * A location the post asks for that the candidate likely misses, above the
 * requirements: $location (Location_notice).
 */
?>
<section class="location-notice">
    <h3><?= out($location['heading']) ?></h3>
    <ul>
        <?php foreach ($location['lines'] as $line): ?>
            <li><strong><?= out($line['text']) ?></strong><?php if ($line['reason'] !== ''): ?> · <?= out($line['reason']) ?><?php endif; ?></li>
        <?php endforeach; ?>
    </ul>
    <p class="muted"><?= out($location['note']) ?></p>
</section>
