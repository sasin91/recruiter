--TEST--
the profile as the plain text applications and résumés are written from: header, about, entries by section, lists
--FILE--
<?php
require_once __DIR__ . '/../Profile_fields.php';

echo Profile_fields::text(
    ['name' => 'Jonas Hansen', 'email' => 'jonas@example.com', 'phone' => null],
    ['title' => 'Software developer', 'location' => 'Slagelse, 4200', 'about' => "First paragraph.\n\nSecond."],
    [
        ['section' => 'experience', 'title' => 'Web Developer', 'organisation' => 'Acme ApS', 'location' => 'Copenhagen', 'url' => '',
         'started_on' => '2024-09-01', 'ended_on' => '2026-02-01', 'summary' => 'Matchmaking platform', 'bullets' => ['Built the ranking'], 'note' => 'Ended in cuts.'],
        ['section' => 'project', 'title' => 'Trongate.cloud', 'organisation' => '', 'location' => '', 'url' => 'https://trongate.cloud',
         'started_on' => '2026-01-01', 'ended_on' => null, 'summary' => '', 'bullets' => ['Hosts this app'], 'note' => ''],
    ],
    ['skill' => ['PHP'], 'language' => [], 'certificate' => []],
    [['label' => 'GitHub', 'url' => 'https://github.com/sasin91']]
), "\n";
?>
--EXPECT--
Jonas Hansen
Software developer
Slagelse, 4200 · jonas@example.com
GitHub: https://github.com/sasin91

First paragraph.

Second.

Experience

Web Developer · Acme ApS
September 2024 – February 2026 — Copenhagen
Matchmaking platform
- Built the ranking
Ended in cuts.

Projects

Trongate.cloud
January 2026 – now — https://trongate.cloud
- Hosts this app

Skills
- PHP
