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
// The admin pages, shown in the side menu and the mobile menu
$admin_nav = [
    'resources/manage/companies' => 'Companies',
    'resources/manage/candidates' => 'Candidates',
    'resources/manage/job_posts' => 'Job posts',
    'resources/manage/job_applications' => 'Applications',
    'resources' => 'All tables',
    'queue/manage' => 'Queue',
    'trongate_administrators/manage' => 'Administrators',
];
// The page's menu entry: a resources/ table with its own entry, else
// "All tables" for the rest of resources/, else the module's.
$current_page = segment(1);
if ($current_page === 'resources') {
    $table_page = 'resources/manage/' . segment(3);
    $current_page = isset($admin_nav[$table_page]) ? $table_page : 'resources';
}
$nav_link = function (string $url, string $label) use ($current_page): string {
    $is_current = $url === $current_page || (!str_starts_with($url, 'resources') && strtok($url, '/') === $current_page);
    $current = $is_current ? ' class="current" aria-current="page"' : '';
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
            <li><?= $nav_link($url, $label) ?></li>
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
    <li><?= $nav_link($url, $label) ?></li>
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
