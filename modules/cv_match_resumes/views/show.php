<h1><?= $headline ?></h1>
<?= flashdata() ?>
<div class="card">
    <div class="card-heading">
        Tailored Résumé Details
    </div>
    <div class="card-body">
        <div class="text-right mb-3">
            <?= anchor($back_url, 'Back', array('class' => 'button alt')) ?>
        </div>
        <div class="detail-grid">
            <div class="detail-row">
                <div class="detail-label">Cv Match Id</div>
                <div class="detail-value"><?= out($cv_match_id) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Name</div>
                <div class="detail-value"><?= out($name) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Title</div>
                <div class="detail-value"><?= out($title) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Location</div>
                <div class="detail-value"><?= out($location) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Phone</div>
                <div class="detail-value"><?= out($phone) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?= out($email) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Links</div>
                <div class="detail-value"><?= out($links) ?></div>
            </div>
            <div class="detail-block">
                <div class="detail-label">Intro</div>
                <div class="detail-content"><?= nl2br(out($intro)) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Education Note</div>
                <div class="detail-value"><?= out($education_note) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Experience Heading</div>
                <div class="detail-value"><?= out($experience_heading) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Skills Heading</div>
                <div class="detail-value"><?= out($skills_heading) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Education Heading</div>
                <div class="detail-value"><?= out($education_heading) ?></div>
            </div>
            <div class="detail-row">
                <div class="detail-label">Languages Heading</div>
                <div class="detail-value"><?= out($languages_heading) ?></div>
            </div>
        </div>
    </div>
</div>
