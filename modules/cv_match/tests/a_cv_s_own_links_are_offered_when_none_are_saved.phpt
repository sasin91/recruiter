--TEST--
a CV's own links (LinkedIn, GitHub, a website) are offered in the Apply dialog when none are saved
--FILE--
<?php
require __DIR__ . '/../Application_delivery.php';
$cv = "Jonas Hansen\nSlagelse · jonas.kerwin.hansen@gmail.com\nFull CV online: sasin91.xyz/cv\n"
    . "https://github.com/sasin91 · https://www.linkedin.com/in/jonas-hansen-2b6828110\n"
    . "Node.js/React, Vue.js. See www.linkedin.com/in/jonas-hansen-2b6828110 or https://jonas.dev/work.\n"
    . "Also gitlab.com/sasin91, email someone@github.com";
foreach (Cv_links::read($cv) as $link) {
    echo "{$link['label']}: {$link['url']}\n";
}
$match = ['id' => 1, 'job_title' => 'Dev', 'job_url' => '', 'cv_text' => $cv, 'application_text' => '', 'resume_text' => '', 'apply_instructions' => null];
$delivery = Application_delivery::from($match);
echo count($delivery['links']), ' links: ', $delivery['links_hint'], "\n";
$delivery = Application_delivery::from($match, [['label' => 'Portfolio', 'url' => 'https://sasin91.xyz']]);
echo count($delivery['links']), " link: {$delivery['links'][0]['label']} [{$delivery['links_hint']}]\n";
$delivery = Application_delivery::from(['cv_text' => 'No links here.'] + $match);
echo $delivery['links_hint'], "\n";
?>
--EXPECT--
GitHub: https://github.com/sasin91
LinkedIn: https://www.linkedin.com/in/jonas-hansen-2b6828110
Website: https://jonas.dev/work
GitLab: https://gitlab.com/sasin91
4 links: From the CV of this match. Links saved on your account page take their place.
1 link: Portfolio []
Add your LinkedIn, GitHub or portfolio on your account page to have them here, ready to copy.
