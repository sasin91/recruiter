<?php /* One entry's form ($form: Profile_page::entry_form), inside profile.php. */ ?>
<?php if ($form['errors']): ?>
    <ul class="validation-errors">
        <?php foreach ($form['errors'] as $error): ?>
            <li><?= out($error) ?></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
<?= form_open('profile/submit_entry' . ($form['id'] !== null ? '/' . $form['id'] : ''), ['class' => 'stack']) ?>
    <?= form_hidden('section', $form['section']) ?>
    <?= form_label($form['section'] === 'project' ? 'Name' : 'Title', ['for' => $form['key'] . '-title']) ?>
    <?= form_input('title', $form['title'], ['id' => $form['key'] . '-title', 'maxlength' => '255', 'required' => true]) ?>
    <?php if ($form['section'] !== 'project'): ?>
        <?= form_label($form['section'] === 'education' ? 'School' : 'Employer', ['for' => $form['key'] . '-organisation']) ?>
        <?= form_input('organisation', $form['organisation'], ['id' => $form['key'] . '-organisation', 'maxlength' => '255']) ?>
    <?php else: ?>
        <?= form_hidden('organisation', $form['organisation']) ?>
        <?= form_label('Link', ['for' => $form['key'] . '-url']) ?>
        <?= form_input('url', $form['url'], ['id' => $form['key'] . '-url', 'maxlength' => '512', 'inputmode' => 'url', 'placeholder' => 'https://…']) ?>
    <?php endif; ?>
    <?= form_label('Location', ['for' => $form['key'] . '-location']) ?>
    <?= form_input('location', $form['location'], ['id' => $form['key'] . '-location', 'maxlength' => '255']) ?>
    <div class="profile-months">
        <span class="stack">
            <?= form_label('Started', ['for' => $form['key'] . '-started']) ?>
            <?= form_month('started', $form['started'], ['id' => $form['key'] . '-started']) ?>
        </span>
        <span class="stack">
            <?= form_label('Ended (empty while ongoing)', ['for' => $form['key'] . '-ended']) ?>
            <?= form_month('ended', $form['ended'], ['id' => $form['key'] . '-ended']) ?>
        </span>
    </div>
    <?= form_label('What the employer or project is', ['for' => $form['key'] . '-summary']) ?>
    <?= form_input('summary', $form['summary'], ['id' => $form['key'] . '-summary', 'maxlength' => '500']) ?>
    <?= form_label('What you did (one per line)', ['for' => $form['key'] . '-bullets']) ?>
    <?= form_textarea('bullets', $form['bullets'], ['id' => $form['key'] . '-bullets', 'rows' => '6']) ?>
    <?= form_label('Note', ['for' => $form['key'] . '-note']) ?>
    <?= form_input('note', $form['note'], ['id' => $form['key'] . '-note', 'maxlength' => '1000']) ?>
    <?= form_submit('save_entry', 'Save', ['class' => 'button primary']) ?>
<?= form_close() ?>
