<!DOCTYPE html>
<html lang="en">
<head>
    <base href="<?= BASE_URL ?>">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/trongate-icons.css">
    <link rel="stylesheet" href="css/trongate.css">
    <link rel="stylesheet" href="templates_module/css/admin.css">
    <script src="js/trongate-mx.min.js"></script>
    <?= $additional_includes_top ?? '' ?>
    <title><?= out($page_title ?? WEBSITE_NAME . ' admin') ?></title>
</head>
<body class="theme-<?= $theme ?? 'default' ?>">

<?php
// The admin pages, shown in the side menu and the mobile menu: url => label,
// or a group label => its pages
$admin_nav = [
    'Companies' => [
        'companies/manage' => 'Companies',
        'company_members/manage' => 'Company members',
        'company_llm_keys/manage' => 'Company AI keys',
    ],
    'Candidates' => [
        'candidates-admin/manage' => 'Candidates',
        'candidate_resumes/manage' => 'Résumés',
        'candidate_resume_terms/manage' => 'Résumé terms',
    ],
    'Jobs' => [
        'job_posts-admin/manage' => 'Job posts',
        'job_post_terms/manage' => 'Job post terms',
        'job_post_member_views/manage' => 'Job post views',
        'job_applications/manage' => 'Applications',
        'job_application_terms/manage' => 'Application terms',
        'job_application_actions/manage' => 'Application actions',
        'attributes/manage' => 'Attributes',
    ],
    'Scoring' => [
        'match_scores/manage' => 'Match scores',
        'match_score_details/manage' => 'Match score details',
    ],
    'CV checker' => [
        'cv_matches/manage' => 'CV matches',
        'cv_match_items/manage' => 'CV match items',
        'cv_match_resumes/manage' => 'Tailored résumés',
        'cv_match_resume_entries/manage' => 'Résumé entries',
        'cv_match_resume_lines/manage' => 'Résumé lines',
        'user_llm_keys/manage' => 'User AI keys',
    ],
    'Taxonomy' => [
        'terms/manage' => 'Terms',
        'term_labels/manage' => 'Term labels',
        'term_relations/manage' => 'Term relations',
        'unmatched_terms/manage' => 'Unmatched terms',
    ],
    'Sign-in' => [
        'trongate_users/manage' => 'Users',
        'trongate_user_levels/manage' => 'User levels',
        'login_attempts/manage' => 'Failed sign-ins',
        'trongate_tokens-admin/manage' => 'Sign-in tokens',
        'password_resets/manage' => 'Password resets',
        'trongate_administrators/manage' => 'Administrators',
    ],
    'queue/manage' => 'Queue',
];
$current_module = segment(1);
$is_current = fn(string $url): bool => strtok($url, '/') === $current_module;
$nav_link = function (string $url, string $label) use ($is_current): string {
    $current = $is_current($url) ? ' class="current" aria-current="page"' : '';
    return '<a href="' . $url . '"' . $current . '>' . $label . '</a>';
};
?>
<header>
    <div class="header-lg">
        <div><?= WEBSITE_NAME ?></div>
        <div class="top-rhs">
            <div>
                <nav>
                    <ul class="top-nav">
                        <li><a href="<?= BASE_URL ?>"><i class="tg tg-home"></i> View site</a></li>
                    </ul>
                </nav>
            </div>
            <div class="top-rhs-selector"><i class="tg tg-user"></i> &#9660;</div>
        </div>
    </div>
    <div class="header-sm">
        <div id="hamburger">&#9776;</div>
        <div><?= WEBSITE_NAME ?></div>
        <div class="top-rhs-selector"><i class="tg tg-user"></i> &#9660;</div>
    </div>
    
    <!-- Admin Settings Dropdown -->
    <div id="admin-settings-dropdown">
        <ul>
            <li><a href="trongate_administrators/update_your_details"><i class="tg tg-user"></i> Update Your Details</a></li>
            <li class="top-border"><a href="trongate_administrators/logout"><i class="tg tg-sign-out"></i> Log Out</a></li>
        </ul>
    </div>
</header>

<aside>
<?php
if (strtolower(ENV) === 'dev') {
    echo Modules::run('trongate_control-flo/draw_flow_trigger');
}
?>
    <nav aria-label="Main navigation">
        <ul class="side-nav-menu">
            <?php foreach ($admin_nav as $url => $label): ?>
            <?php if (is_array($label)):
                $open = array_filter(array_keys($label), $is_current) !== []; ?>
            <li class="nav-dropdown<?= $open ? ' open' : '' ?>">
                <div><span><?= $url ?></span><span class="arrow-icon<?= $open ? ' rotate' : '' ?>">&#9660;</span></div>
                <ul class="nav-submenu"<?= $open ? ' style="max-height: none"' : '' ?>>
                    <?php foreach ($label as $sub_url => $sub_label): ?>
                    <li><?= $nav_link($sub_url, $sub_label) ?></li>
                    <?php endforeach; ?>
                </ul>
            </li>
            <?php else: ?>
            <li><?= $nav_link($url, $label) ?></li>
            <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </nav>
</aside>

<main>
    <div class="center-stage"><?= display($data) ?></div>
</main>

<footer>
    <div class="footer-lg">
        <div class="footer-left">
            <span>&copy; <?= date('Y').' '.WEBSITE_NAME ?></span>
            <span class="separator">|</span>
            <a href="https://trongate.io/" target="_blank">Powered by Trongate</a>
        </div>
        <div class="footer-right">
            <a href="https://trongate.io/documentation" target="_blank">Documentation</a>
            <a href="https://github.com/trongate/trongate-framework" target="_blank">GitHub</a>
        </div>
    </div>
    
    <div class="footer-sm">
        <div>&copy; <?= date('Y').' '.WEBSITE_NAME ?></div>
        <div class="footer-sm-links">
            <a href="https://trongate.io/" target="_blank">Powered by Trongate</a>
            <span class="separator">|</span>
            <a href="https://trongate.io/documentation" target="_blank">Documentation</a>
            <span class="separator">|</span>
            <a href="https://github.com/trongate/trongate-framework" target="_blank">GitHub</a>
        </div>
    </div>
</footer>

<!-- Mobile slide navigation -->
<div class="nav-overlay" id="nav-overlay"></div>

<nav class="slide-nav" id="slide-nav" aria-label="Navigation menu">
  <div class="slide-nav-header">
    <div class="slide-nav-close" id="close-slide-nav">&times;</div>
  </div>

  <ul class="slide-nav-list">
    <?php foreach ($admin_nav as $url => $label): ?>
    <?php foreach (is_array($label) ? $label : [$url => $label] as $sub_url => $sub_label): ?>
    <li><?= $nav_link($sub_url, $sub_label) ?></li>
    <?php endforeach; ?>
    <?php endforeach; ?>
    <li><a href="trongate_administrators/update_your_details">Update your details</a></li>
    <li><a href="<?= BASE_URL ?>">View site</a></li>
    <li><a href="trongate_administrators/logout">Log out</a></li>
  </ul>
</nav>

<script src="templates_module/js/admin.js"></script>
<?= $additional_includes_btm ?? '' ?>
</body>
</html>
