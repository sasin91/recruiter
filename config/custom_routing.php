<?php
$routes = [
    'tg-admin' => 'login/login/tg-admin',
    'sign-in' => 'login/login/sign-in',
    'register' => 'candidates/register',
    'company-sign-in' => 'login/login/company-sign-in',
    'company-sign-up' => 'company/register',
    'jobs/(:any)/apply' => 'applications/apply/$1',
    'jobs/(:any)' => 'job_posts/show/$1'
];
define('CUSTOM_ROUTES', $routes);