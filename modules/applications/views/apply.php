<?php
/**
 * Applying for $post, at $step: sign_in, closed, applied, cv (the CV form)
 * or send (the match preview, cover letter and Send).
 */
$title = 'Apply: ' . $post['title'];
require __DIR__ . '/header.php';
$verdict_names = ['met' => 'In your CV', 'partial' => 'Something close', 'missing' => 'Not found'];
$tag_names = ['top' => 'Top match', 'good' => 'Good match', 'medium' => 'Medium match', 'poor' => 'Weak match'];
?>
<main class="narrow-wide">
    <div class="post-head">
        <p class="company"><?= out($post['company_name']) ?></p>
        <h1><?= out($post['title']) ?></h1>
        <p><a href="jobs/<?= out($post['public_token']) ?>">Read the job post</a></p>
    </div>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?= validation_errors() ?>

    <?php if ($step === 'sign_in'): ?>
        <section class="panel">
            <h2>Sign in to apply</h2>
            <p>You apply with a free candidate account. Your CV is kept on it, so the next application is two clicks.</p>
            <div class="actions-bar">
                <a class="button primary" href="register">Create an account</a>
                <a class="button" href="sign-in">Sign in</a>
            </div>
        </section>

    <?php elseif ($step === 'closed'): ?>
        <p class="notice"><?= $post['status'] === 'paused' ? "This job isn't taking applications right now." : 'This job is closed and no longer takes applications.' ?></p>

    <?php elseif ($step === 'applied'): ?>
        <section class="panel">
            <h2>You've applied</h2>
            <p>Sent <?= date('j M Y', (int) $application['submitted_at']) ?>.</p>
            <a class="button" href="applications">My applications</a>
        </section>

    <?php elseif ($step === 'cv'): ?>
        <?= form_open('applications/submit_cv/' . out($post['public_token']), ['id' => 'cv-form', 'class' => 'panel']) ?>
            <h2>Your CV</h2>
            <p class="muted">Upload a PDF or text file, or paste it. It's kept on your account for your next applications<?= $profile ? ' and replaces the one you gave before' : '' ?>.</p>
            <div class="field">
                <label for="cv-file">CV file</label>
                <input type="file" id="cv-file" accept=".pdf,.txt,.md,.html,.htm,application/pdf,text/plain">
                <span class="muted small" id="cv-status" role="status"></span>
            </div>
            <div class="field">
                <label for="cv_text">Or paste it</label>
                <textarea id="cv_text" name="cv_text" rows="14" required maxlength="40000"><?= out((string) post('cv_text')) ?></textarea>
            </div>
            <input type="hidden" name="cv_name" id="cv_name" value="<?= out((string) post('cv_name', true)) ?>">
            <div class="actions-bar">
                <button type="submit" class="button primary">Read my CV</button>
                <?php if ($profile): ?><a class="button" href="jobs/<?= out($post['public_token']) ?>/apply">Keep my saved CV</a><?php endif; ?>
            </div>
        <?= form_close() ?>
        <script type="module" src="applications_module/js/apply.js"></script>

    <?php else: ?>
        <?php $result = $preview['result']; $pct = (int) round($result['index'] * 100); ?>
        <section class="panel">
            <div class="match-head">
                <div class="score-ring <?= $result['tag'] ?>" style="--pct: <?= $pct ?>"><span><?= $pct ?></span></div>
                <div>
                    <h2><?= $tag_names[$result['tag']] ?></h2>
                    <p class="muted small">How your CV matches the post on the words it names. The company's own check also reads the whole CV, so related experience can still count.</p>
                </div>
            </div>
            <?php foreach (['requirements' => 'What they ask for', 'skills' => 'Nice to have'] as $group => $heading): ?>
                <?php if ($preview['groups'][$group]): ?>
                    <h3><?= $heading ?></h3>
                    <ul class="verdicts">
                        <?php foreach ($preview['groups'][$group] as $item): ?>
                            <?php $v = $preview['verdicts'][$item['id']]['verdict'] ?? 'missing'; ?>
                            <li class="<?= $v ?>"><span class="mark" aria-hidden="true"></span><?= out($item['text']) ?> <span class="muted small"><?= $verdict_names[$v] ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>

        <?= form_open('applications/submit_apply/' . out($post['public_token']), ['class' => 'panel']) ?>
            <h2>Send your application</h2>
            <p class="muted small">
                With your CV <strong><?= out($profile['cv_name'] !== '' ? $profile['cv_name'] : 'as pasted') ?></strong>, saved <?= date('j M Y', (int) $profile['updated_at']) ?>.
                <a href="jobs/<?= out($post['public_token']) ?>/apply?cv=new">Use another CV</a>
            </p>
            <div class="field">
                <label for="cover_letter">Cover letter <span class="muted">(optional)</span></label>
                <textarea id="cover_letter" name="cover_letter" rows="8" maxlength="5000" placeholder="Why this job, and anything your CV doesn't say."><?= out((string) $cover_letter) ?></textarea>
            </div>
            <p class="muted small">The company sees your name, email, CV and cover letter.</p>
            <button type="submit" class="button primary big">Send application</button>
        <?= form_close() ?>
    <?php endif; ?>
</main>
</body>
</html>
