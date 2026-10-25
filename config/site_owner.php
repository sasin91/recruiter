<?php
//Settings to do with the identification of the site owner
// The owner's name and contact email come from the environment (OUR_NAME,
// OUR_EMAIL_ADDRESS), so the public repository carries no personal details.
// The privacy page (modules/privacy) names them as the data controller.
define('WEBSITE_NAME', 'Recruiter');
define('OUR_NAME', getenv('OUR_NAME') ?: ($_SERVER['OUR_NAME'] ?? ''));
define('OUR_TELNUM', '');
define('OUR_ADDRESS', '');
define('OUR_EMAIL_ADDRESS', getenv('OUR_EMAIL_ADDRESS') ?: ($_SERVER['OUR_EMAIL_ADDRESS'] ?? ''));
