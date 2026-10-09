<?php
/**
 * The public page of a job post (/jobs/{token}), or its preview for staff
 * ($preview). Requirements show as tags: required ones first, alternatives
 * joined with "or", then nice-to-haves and personal qualities.
 */
$facts = array_filter([
    Job_post_rules::WORK_HOURS[$post['work_hours'] ?? ''] ?? '',
    Job_post_rules::WORKPLACE[$post['workplace_flexibility'] ?? ''] ?? '',
    $post['postal_code'] ? 'Postal code ' . $post['postal_code'] : '',
], fn($fact) => $fact !== '' && $fact !== 'Not stated');

// Requirement rows as tags: an OR-group is one tag.
$tag_lists = ['required' => [], 'nice' => [], 'soft' => []];
$groups = [];
foreach ($rows as $row) {
    if (!isset(Job_post_rules::KINDS[$row['kind']])) {
        continue;
    }
    $years = $row['min_years'] && !preg_match('/\d/', $row['raw_text']) ? ' <span class="years">' . (int) $row['min_years'] . '+ yrs</span>' : '';
    $text = out($row['raw_text']) . $years
        . ($row['min_level'] ? ' <span class="years">(' . out($row['min_level']) . ')</span>' : '');
    $list = $row['kind'] === 'soft_skill' ? 'soft' : ($row['is_required'] ? 'required' : 'nice');
    if ($row['alt_group'] !== null && $list !== 'soft') {
        $key = $list . $row['alt_group'];
        if (isset($groups[$key])) {
            $tag_lists[$list][$groups[$key]] .= ' <span class="or">or</span> ' . $text;
            continue;
        }
        $groups[$key] = count($tag_lists[$list]);
    }
    $tag_lists[$list][] = $text;
}
$responsibilities = array_column(array_filter($rows, fn($r) => $r['kind'] === 'responsibility_area'), 'raw_text');
$titles = ['required' => 'What you bring', 'nice' => 'Nice to have', 'soft' => 'Who you are'];
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
        <?php if ($facts): ?>
            <ul class="facts"><?php foreach ($facts as $fact): ?><li><?= out($fact) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    </div>

    <?php if (in_array($post['status'], ['closed', 'archived'], true)): ?>
        <p class="notice">This job is closed and no longer takes applications.</p>
    <?php elseif ($post['status'] === 'paused'): ?>
        <p class="notice">This job isn't taking applications right now.</p>
    <?php endif; ?>

    <?php if ($post['pitch']): ?>
        <div class="pitch"><?= out($post['pitch']) ?></div>
    <?php endif; ?>

    <?php if ($responsibilities): ?>
        <h2>The job</h2>
        <ul><?php foreach ($responsibilities as $line): ?><li><?= out($line) ?></li><?php endforeach; ?></ul>
    <?php endif; ?>

    <?php foreach ($tag_lists as $key => $tags): ?>
        <?php if ($tags): ?>
            <h2><?= $titles[$key] ?></h2>
            <ul class="tags"><?php foreach ($tags as $tag): ?><li class="<?= $key === 'required' ? 'required' : '' ?>"><?= $tag ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
    <?php endforeach; ?>

    <?php if ($post['status'] === 'active' || $preview): ?>
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
