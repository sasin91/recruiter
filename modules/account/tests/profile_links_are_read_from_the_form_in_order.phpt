--TEST--
profile links are read from the form in order: empty rows dropped, https:// added, bad ones refused
--FILE--
<?php
require_once __DIR__ . '/../Profile_links.php';
$read = Profile_links::read(
    ['LinkedIn', 'GitHub', 'Portfolio', '', 'github', 'Blog', 'Mastodon'],
    ['https://www.linkedin.com/in/jonas-hansen-2b6828110', ' github.com/sasin91 ', '', 'https://example.dk', 'https://gitlab.com/x', 'not a link', 'javascript:alert(1)']
);
foreach ($read['links'] as $link) {
    echo "{$link['label']}: {$link['url']}\n";
}
echo implode("\n", $read['errors']), "\n--\n";

// The form: saved links first, then the suggested labels not used yet, then an empty row.
foreach (Profile_links::form_rows([['label' => 'GitHub', 'url' => 'https://github.com/sasin91']]) as $row) {
    echo "[{$row['label']}] [{$row['url']}]\n";
}
?>
--EXPECT--
LinkedIn: https://www.linkedin.com/in/jonas-hansen-2b6828110
GitHub: https://github.com/sasin91
Give https://example.dk a name, like LinkedIn.
There are two links named github.
The Blog link isn't a web address (https://...).
The Mastodon link isn't a web address (https://...).
--
[GitHub] [https://github.com/sasin91]
[LinkedIn] []
[Portfolio] []
[] []
