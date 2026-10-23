--TEST--
a CV the model read becomes the profile's rows: entries by section with month dates, lists, and nothing without entries
--FILE--
<?php
require_once __DIR__ . '/../Profile_fields.php';

$profile = Profile_fields::from_cv([
    'title' => ' Software developer ',
    'location' => 'Slagelse, 4200',
    'about' => ['I have been working with PHP since 2015.', ' '],
    'entries' => [
        ['section' => 'experience', 'title' => 'Web Developer', 'organisation' => 'Acme ApS', 'location' => 'Copenhagen', 'url' => '',
         'started' => '2024-09', 'ended' => '', 'summary' => 'Matchmaking platform', 'bullets' => ['Upgraded Symfony 6 to 7', ''], 'note' => ''],
        ['section' => 'project', 'title' => 'Trongate.cloud', 'organisation' => '', 'location' => '', 'url' => 'https://trongate.cloud',
         'started' => '2026', 'ended' => 'now', 'summary' => 'Hosting for Trongate apps', 'bullets' => [], 'note' => ''],
        ['section' => 'hobby', 'title' => 'Skipped: no such section'],
        ['section' => 'education', 'title' => '', 'started' => '2014-02'],
    ],
    'skills' => ['PHP', 'Laravel'],
    'languages' => ['Danish'],
    'certificates' => [],
]);
foreach ($profile['entries'] as $entry) {
    echo "{$entry['section']}: {$entry['title']} ", var_export($entry['started_on'], true), ' – ', var_export($entry['ended_on'], true), ' ', count($entry['bullets']), " bullet(s)\n";
}
echo $profile['title'], ' | ', $profile['about'], ' | ', implode(',', $profile['lines']['skill']), ' | ', count($profile['lines']['certificate']), "\n";

var_dump(Profile_fields::from_cv(['entries' => []]));
var_dump(Profile_fields::month_date('2024-13'), Profile_fields::month_date('nu'));
?>
--EXPECT--
experience: Web Developer '2024-09-01' – NULL 1 bullet(s)
project: Trongate.cloud '2026-01-01' – NULL 0 bullet(s)
Software developer | I have been working with PHP since 2015. | PHP,Laravel | 0
NULL
NULL
NULL
