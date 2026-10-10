<?php
//The main config file
define('BASE_URL', '****');
// 'dev' opens Trongate's developer tools (setup wizard, code generators, SQL
// runner, admin auto-login), so it must never answer the internet. Set APP_ENV
// to choose; without it only the command line and a browser on this machine
// get 'dev', and a deployed site is 'prod'.
$app_env = (static function (): string {
    $env = getenv('APP_ENV') ?: ($_SERVER['APP_ENV'] ?? '');
    if ($env !== '') {
        return $env;
    }
    if (PHP_SAPI === 'cli') {
        return 'dev';
    }
    $local = in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    $forwarded = ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['HTTP_FORWARDED'] ?? '') !== '';
    return $local && !$forwarded ? 'dev' : 'prod';
})();
// Keep this define on one line: the host rewrites config lines one at a time.
define('ENV', $app_env);
define('DEFAULT_MODULE', 'welcome');
define('DEFAULT_METHOD', 'index');
define('MODULE_ASSETS_TRIGGER', '_module');
define('ERROR_404', 'error_pages/not_found');
