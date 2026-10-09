--TEST--
without a job title, the free reader's first line is the title
--FILE--
<?php
require __DIR__ . '/setup.inc';
$rows = Job_post_rules::from_reading(['job_title' => '', 'heading' => 'Lagermedarbejder søges', 'requirements' => []]);
var_dump($rows[0]['kind'], $rows[0]['raw_text']);
?>
--EXPECT--
string(5) "title"
string(23) "Lagermedarbejder søges"
