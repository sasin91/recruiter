<?php
$title = $post['title'];
require __DIR__ . '/../../company/views/header.php';
$status_names = ['draft' => 'Draft', 'active' => 'Live', 'paused' => 'Paused', 'closed' => 'Closed', 'archived' => 'Archived'];
$action_names = ['publish' => 'Publish', 'pause' => 'Pause', 'resume' => 'Take applications again', 'close' => 'Close', 'reopen' => 'Reopen', 'archive' => 'Archive'];
$is_draft = $post['status'] === 'draft';

/** A one-button POST form to $method on this post. */
$post_button = function (string $method, string $label, array $fields = [], string $class = 'button') use ($post) {
    $html = form_open("job_posts/$method/" . (int) $post['id'], ['class' => 'inline']);
    foreach ($fields as $name => $value) {
        $html .= form_hidden($name, $value);
    }
    return $html . form_submit('submit', $label, ['class' => $class]) . form_close();
};
?>
<main>
    <div class="page-head">
        <div>
            <h1><?= out($post['title']) ?></h1>
            <p class="sub">
                <span class="status <?= out($post['status']) ?>"><?= $status_names[$post['status']] ?? out($post['status']) ?></span>
                <?php if ((int) $post['version'] > 1): ?><span class="muted small">Version <?= (int) $post['version'] ?></span><?php endif; ?>
                <span class="muted small">Read by <?= $post['extractor'] === 'free_reader' ? 'the free reader' : 'AI (' . out($post['extractor']) . ')' ?></span>
            </p>
        </div>
        <div class="actions-bar">
            <?php if (!$is_draft): ?>
                <a class="button primary" href="matchmaker/post/<?= (int) $post['id'] ?>">Candidates</a>
            <?php endif; ?>
            <a class="button" href="job_posts/preview/<?= (int) $post['id'] ?>" target="_blank" rel="noopener">Preview</a>
        </div>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?php if ($errors): ?>
        <div class="notice errors" role="alert"><strong>Not saved yet:</strong>
            <ul><?php foreach ($errors as $error): ?><li><?= out($error) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($public_url): ?>
        <div class="panel">
            <h2>The post's link</h2>
            <p class="muted">Share it wherever you advertise: candidates apply from it.</p>
            <input type="text" class="copy-field" readonly value="<?= out($public_url) ?>" onclick="this.select()" aria-label="Public link">
        </div>
    <?php endif; ?>

    <?= form_open('job_posts/submit_review/' . (int) $post['id'], ['id' => 'review-form']) ?>
        <section class="panel">
            <h2>Title and basics</h2>
            <div class="form-grid">
                <div class="field">
                    <label for="title">Job title</label>
                    <input type="text" id="title" name="title" value="<?= out($title_row['raw_text']) ?>" required maxlength="255">
                </div>
                <div class="field">
                    <label for="title_en">In English</label>
                    <input type="text" id="title_en" name="title_en" value="<?= out((string) $title_row['english']) ?>" maxlength="255" placeholder="For the matching">
                </div>
                <div class="field">
                    <label for="language">The post's language</label>
                    <?= form_dropdown('language', Job_post_rules::LANGUAGES, $post['language'], ['id' => 'language']) ?>
                </div>
                <div class="field">
                    <label for="postal_code">Postal code</label>
                    <input type="text" id="postal_code" name="postal_code" value="<?= out((string) $post['postal_code']) ?>" inputmode="numeric" pattern="[0-9]{4}" maxlength="4" placeholder="e.g. 8000">
                </div>
                <div class="field">
                    <label for="work_hours">Hours</label>
                    <?= form_dropdown('work_hours', Job_post_rules::WORK_HOURS, (string) $post['work_hours'], ['id' => 'work_hours']) ?>
                </div>
                <div class="field">
                    <label for="workplace_flexibility">Where</label>
                    <?= form_dropdown('workplace_flexibility', Job_post_rules::WORKPLACE, (string) $post['workplace_flexibility'], ['id' => 'workplace_flexibility']) ?>
                </div>
            </div>
        </section>

        <section class="panel">
            <h2>Requirements</h2>
            <p class="muted">Candidates are matched on these. Uncheck <em>Required</em> for nice-to-haves. Give alternatives the same letter under <em>OR</em> ("chef <em>or</em> 5 years in a kitchen": both A). Personal qualities are shown but not scored. <em>Level</em> is for a level asked for, like "fluent" or "master's degree".</p>
            <table class="req-table" id="requirements">
                <thead>
                    <tr><th>Requirement</th><th>In English</th><th>Kind</th><th class="check">Required</th><th>OR</th><th>Years</th><th>Level</th><th class="check">Remove</th></tr>
                </thead>
                <tbody>
                <?php foreach ($requirements as $i => $r): ?>
                    <tr>
                        <td class="wide" data-label="Requirement"><input type="text" name="requirements[<?= $i ?>][value]" value="<?= out($r['raw_text']) ?>" maxlength="255" aria-label="Requirement <?= $i + 1 ?>"></td>
                        <td class="wide" data-label="In English"><input type="text" name="requirements[<?= $i ?>][english]" value="<?= out((string) $r['english']) ?>" maxlength="255" aria-label="Requirement <?= $i + 1 ?> in English"></td>
                        <td data-label="Kind"><?= form_dropdown("requirements[$i][kind]", Job_post_rules::KINDS, $r['kind'], ['aria-label' => 'Kind']) ?></td>
                        <td class="check" data-label="Required"><input type="checkbox" name="requirements[<?= $i ?>][required]" value="1"<?= $r['is_required'] ? ' checked' : '' ?> aria-label="Required"></td>
                        <td data-label="OR"><input type="text" class="group-input" name="requirements[<?= $i ?>][group]" value="<?= Job_post_rules::group_letter($r['alt_group']) ?>" maxlength="1" aria-label="OR-group letter"></td>
                        <td data-label="Years"><input type="text" class="narrow-input" name="requirements[<?= $i ?>][min_years]" value="<?= $r['min_years'] ? (int) $r['min_years'] : '' ?>" inputmode="numeric" maxlength="2" aria-label="Years"></td>
                        <td data-label="Level"><input type="text" class="level-input" name="requirements[<?= $i ?>][min_level]" value="<?= out((string) $r['min_level']) ?>" maxlength="24" aria-label="Level"></td>
                        <td class="check" data-label="Remove"><input type="checkbox" name="requirements[<?= $i ?>][remove]" value="1" aria-label="Remove"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button small" id="add-requirement">Add a requirement</button></p>
        </section>

        <section class="panel">
            <h2>Responsibilities</h2>
            <div class="field">
                <label for="responsibilities" class="muted">One per line, a few words each.</label>
                <textarea id="responsibilities" name="responsibilities" rows="5"><?= out(implode("\n", $responsibilities)) ?></textarea>
            </div>
        </section>

        <section class="panel">
            <h2>The pitch</h2>
            <div class="field">
                <label for="pitch" class="muted">What candidates read on the post's page: the job, the team, what you offer.</label>
                <textarea id="pitch" name="pitch" rows="10"><?= out((string) $post['pitch']) ?></textarea>
            </div>
            <details>
                <summary class="muted">The text it was read from</summary>
                <p class="pitch muted small"><?= out($post['raw_text']) ?></p>
            </details>
        </section>

        <div class="sticky-actions actions-bar">
            <button type="submit" class="button" name="then" value="save">Save</button>
            <?php if ($is_draft): ?>
                <button type="submit" class="button primary" name="then" value="publish">Save and publish</button>
            <?php endif; ?>
            <?php if (!$is_draft && $post['status'] !== 'archived'): ?>
                <span class="muted small">Changing the requirements of a live post makes a new version.</span>
            <?php endif; ?>
        </div>
    <?= form_close() ?>

    <section class="panel">
        <h2>The post</h2>
        <div class="actions-bar">
            <?php foreach ($actions as $action): ?>
                <?php if ($action !== 'publish'): ?>
                    <?= $post_button('submit_status', $action_names[$action], ['action' => $action]) ?>
                <?php endif; ?>
            <?php endforeach; ?>
            <?= $post_button('submit_duplicate', 'Duplicate') ?>
            <?php if ($is_draft): ?>
                <?= $post_button('submit_delete', 'Delete draft') ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<script type="module" src="job_posts_module/js/review.js"></script>
</body>
</html>
