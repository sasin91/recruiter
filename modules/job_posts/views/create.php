<?php
$title = 'New job post';
require __DIR__ . '/../../company/views/header.php';
?>
<main>
    <div class="page-head">
        <div>
            <h1>New job post</h1>
            <p class="sub muted">Paste the ad or give its link. It's read into a requirements list you check before publishing.</p>
        </div>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?= validation_errors() ?>

    <?php if (!$has_key): ?>
        <p class="notice">No AI key is saved, so the free reader reads the post: it finds named skills and years of experience, and you add the rest by hand.<?= $member['role'] === 'owner' ? ' <a href="company/settings">Add a key</a> to have the AI read whole sentences.' : '' ?></p>
    <?php endif; ?>

    <?= form_open('job_posts/submit_create', ['class' => 'panel', 'id' => 'create-form']) ?>
        <div class="field">
            <label for="text">The job post</label>
            <textarea id="text" name="text" rows="16" placeholder="Paste the whole ad: title, what the job is, what you ask for…"><?= out((string) $text) ?></textarea>
        </div>
        <p class="or-divider">or</p>
        <div class="field">
            <label for="url">Its link</label>
            <input type="url" id="url" name="url" value="<?= out((string) $url) ?>" placeholder="https://…">
            <span class="muted small">A public page. If the page can't be read, paste the text instead.</span>
        </div>
        <p class="actions-bar">
            <button type="submit" class="button primary" id="read-button">Read the post</button>
            <a class="button" href="company">Cancel</a>
            <span class="muted small" id="read-status" hidden>Reading… this can take a little while.</span>
        </p>
    <?= form_close() ?>
</main>
<script>
document.getElementById("create-form").addEventListener("submit", () => {
  document.getElementById("read-button").disabled = true;
  document.getElementById("read-status").hidden = false;
});
</script>
</body>
</html>
