<?php
/**
 * What the post asks of applicants beyond a click, above the requirements:
 * $apply (Apply_notice), quoting the post.
 */
?>
<section class="apply-notice<?= $apply['trap'] ? ' trap' : '' ?>">
    <h3><?= out($apply['heading']) ?></h3>
    <?php if ($apply['note'] !== ''): ?>
        <p class="muted"><?= out($apply['note']) ?></p>
    <?php endif; ?>
    <ul>
        <?php if ($apply['email'] !== ''): ?>
            <li>Send it to <a href="<?= out($apply['mailto']) ?>"><?= out($apply['email']) ?></a></li>
        <?php endif; ?>
        <?php if ($apply['subject'] !== ''): ?>
            <li>Subject line: <code><?= out($apply['subject']) ?></code></li>
        <?php endif; ?>
        <?php if ($apply['keyword'] !== ''): ?>
            <li>Include: <code><?= out($apply['keyword']) ?></code></li>
        <?php endif; ?>
        <?php if ($apply['asks'] !== ''): ?>
            <li><?= out($apply['asks']) ?></li>
        <?php endif; ?>
    </ul>
    <blockquote>
        <?php foreach ($apply['lines'] as $line): ?>
            <p><?= out($line) ?></p>
        <?php endforeach; ?>
    </blockquote>
</section>
