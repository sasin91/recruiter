<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your profile · Recruiter</title>
    <link rel="stylesheet" href="welcome_module/css/site.css">
    <link rel="stylesheet" href="profile_module/css/profile.css">
</head>
<body>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
    <nav>
        <a href="cv_match">CV match</a>
        <a href="job_scan">Job scan</a>
        <a href="account">Account</a>
        <a href="login/logout">Sign out</a>
    </nav>
</header>
<main class="narrow profile">
    <h1>Your profile</h1>
    <p class="muted">Your job applications and tailored résumés are written from this profile, not from the CV you match with. Fill it in from your CV, then add what the CV leaves out, like projects of your own.</p>

    <?= flashdata('<p class="notice">', '</p>') ?>
    <?= validation_errors() ?>

    <details class="profile-fill"<?= $page['has_profile'] ? '' : ' open' ?>>
        <summary><?= $page['has_profile'] ? 'Fill it in again from a CV' : 'Fill it in from your CV' ?></summary>
        <?php if ($page['filled_note'] !== ''): ?>
            <p class="muted small"><?= out($page['filled_note']) ?></p>
        <?php endif; ?>
        <?= form_open('profile/submit_fill', ['class' => 'stack', 'id' => 'profile-fill']) ?>
            <?= form_label('CV (a PDF or text file, or paste it)', ['for' => 'cv_file']) ?>
            <input type="file" id="cv_file" accept=".pdf,.txt,.md,application/pdf,text/plain">
            <?php if ($page['fill']['note'] !== ''): ?>
                <span class="muted small"><?= out($page['fill']['note']) ?></span>
            <?php endif; ?>
            <?= form_hidden('cv_name', $page['fill']['cv_name']) ?>
            <?= form_textarea('cv_text', $page['fill']['cv_text'], ['id' => 'cv_text', 'rows' => '10', 'aria-label' => 'CV text', 'required' => true]) ?>
            <span class="muted small">Read on your own API key. It takes up to a minute. Leave out your CPR number, health, union membership and photos (<a href="privacy#what">why</a>).</span>
            <?php if ($page['has_profile']): ?>
                <label><?= form_checkbox('replace', 1, false, ['required' => true]) ?> Replace my profile with what this CV says (entries added by hand are removed)</label>
            <?php endif; ?>
            <?= form_submit('fill', 'Read the CV into my profile', ['class' => 'button primary']) ?>
        <?= form_close() ?>
    </details>

    <section>
        <h2 id="about-you">About you</h2>
        <?= form_open('profile/submit_header', ['class' => 'stack']) ?>
            <?= form_label('Your name', ['for' => 'name']) ?>
            <?= form_input('name', $page['header']['name'], ['id' => 'name', 'maxlength' => '255', 'required' => true]) ?>
            <?= form_label('Phone', ['for' => 'phone']) ?>
            <?= form_input('phone', $page['header']['phone'], ['id' => 'phone', 'maxlength' => '32', 'inputmode' => 'tel']) ?>
            <?= form_label('Professional title', ['for' => 'title']) ?>
            <?= form_input('title', $page['header']['title'], ['id' => 'title', 'maxlength' => '255', 'placeholder' => 'Software developer']) ?>
            <?= form_label('Where you live', ['for' => 'location']) ?>
            <?= form_input('location', $page['header']['location'], ['id' => 'location', 'maxlength' => '255', 'placeholder' => 'Slagelse, 4200']) ?>
            <?= form_label('About you', ['for' => 'about']) ?>
            <?= form_textarea('about', $page['header']['about'], ['id' => 'about', 'rows' => '6']) ?>
            <?= form_submit('save_header', 'Save', ['class' => 'button']) ?>
        <?= form_close() ?>
    </section>

    <?php foreach ($page['sections'] as $section): ?>
    <section>
        <h2 id="<?= $section['section'] ?>"><?= out($section['heading']) ?></h2>
        <?php foreach ($section['entries'] as $form): ?>
            <details class="profile-entry"<?= $form['open'] ? ' open' : '' ?>>
                <summary><?= out($form['line']) ?></summary>
                <?php include __DIR__ . '/entry_form.php'; ?>
                <?= form_open('profile/submit_delete_entry/' . $form['id'], ['class' => 'profile-delete']) ?>
                    <?= form_submit('delete', 'Delete', ['class' => 'button small']) ?>
                <?= form_close() ?>
            </details>
        <?php endforeach; ?>
        <details class="profile-entry profile-new"<?= $section['new']['open'] ? ' open' : '' ?>>
            <summary><?= out($section['add_label']) ?></summary>
            <?php $form = $section['new']; include __DIR__ . '/entry_form.php'; ?>
        </details>
    </section>
    <?php endforeach; ?>

    <section>
        <h2 id="lists">Skills, languages and certificates</h2>
        <?= form_open('profile/submit_lists', ['class' => 'stack']) ?>
            <?php foreach ($page['lists'] as $list): ?>
                <?= form_label($list['label'] . ' (one per line)', ['for' => 'list-' . $list['kind']]) ?>
                <?= form_textarea($list['kind'], $list['text'], ['id' => 'list-' . $list['kind'], 'rows' => '5']) ?>
            <?php endforeach; ?>
            <?= form_submit('save_lists', 'Save', ['class' => 'button']) ?>
        <?= form_close() ?>
    </section>
</main>
<script type="module" src="profile_module/js/profile.js"></script>
</body>
</html>
