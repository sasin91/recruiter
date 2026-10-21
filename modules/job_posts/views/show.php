<?php
/**
 * The public page of a job post (/jobs/{token}), or its preview for staff
 * ($preview): $post and $page (Public_job_post::from).
 */
?>
<!DOCTYPE html>
<html lang="<?= out($post['language']) ?>">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= out($post['title']) ?> · <?= out($post['company_name']) ?></title>
    <?php if ($preview): ?><meta name="robots" content="noindex"><?php endif; ?>
    <link rel="stylesheet" href="welcome_module/css/site.css">
    <link rel="stylesheet" href="company_module/css/company.css">
</head>
<body>
<?php if ($preview): ?>
    <div class="preview-banner">Preview: this is how candidates see the post<?= $post['status'] === 'draft' ? ' once it is published' : '' ?>.</div>
<?php endif; ?>
<header class="site-header">
    <a class="brand" href="<?= BASE_URL ?>">Recruiter</a>
</header>
<main>
    <div class="post-head">
        <p class="company"><?= out($post['company_name']) ?></p>
        <h1><?= out($post['title']) ?></h1>
        <?php if ($page['facts']): ?>
            <ul class="facts"><?php foreach ($page['facts'] as $fact): ?><li><?= out($fact) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>

    <?php if ($page['notice'] !== ''): ?>
        <p class="notice"><?= out($page['notice']) ?></p>
    <?php endif; ?>

    <?php if ($post['pitch']): ?>
        <div class="pitch"><?= out($post['pitch']) ?></div>
    <?php endif; ?>

    <?php if ($page['responsibilities']): ?>
        <h2>The job</h2>
        <ul><?php foreach ($page['responsibilities'] as $line): ?><li><?= out($line) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <?php foreach ($page['lists'] as $list): ?>
        <h2><?= out($list['title']) ?></h2>
        <ul class="tags"><?php foreach ($list['tags'] as $tag): ?><li class="<?= $list['required'] ? 'required' : '' ?>"><?php foreach ($tag as $i => $alt): ?><?= $i ? ' <span class="or">or</span> ' : '' ?><?= out($alt['text']) ?><?php if ($alt['years'] !== ''): ?> <span class="years"><?= out($alt['years']) ?></span><?php endif; ?><?php if ($alt['level'] !== ''): ?> <span class="years"><?= out($alt['level']) ?></span><?php endif; ?><?php endforeach; ?></li><?php endforeach; ?></ul>
    <?php endforeach; ?>

    <?php if ($page['takes_applications'] || $preview): ?>
        <div class="apply-box">
            <div>
                <strong>Interested?</strong><br>
                <span class="muted">Apply with your CV. You'll see how well you match before you send it.</span>
            </div>
            <?php if ($preview): ?>
                <span class="button primary" aria-disabled="true">Apply</span>
            <?php else: ?>
                <a class="button primary big" href="jobs/<?= out($post['public_token']) ?>/apply">Apply</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
