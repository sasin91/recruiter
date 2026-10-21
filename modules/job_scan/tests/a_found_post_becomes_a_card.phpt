--TEST--
a found post becomes a card: heading, meta line, score or status, buttons
--FILE--
<?php
require_once __DIR__ . '/../Found_post_card.php';
$found_at = mktime(8, 12, 0, 10, 21, 2026);
$matched = ['id' => '3', 'title' => 'Backend-udvikler', 'url' => 'https://jobnet.dk/1', 'company' => 'Nordisk Fragt', 'location' => '',
    'found_at' => (string) $found_at, 'status' => 'matched', 'score' => '0.814', 'tag' => 'good', 'error' => '', 'cv_match_id' => '7', 'resume_written_at' => '1'];
$card = Found_post_card::from($matched);
echo $card['heading'], "\n", $card['meta'], "\n", $card['score']['percent'], ' ', $card['score']['tag_name'], "\n", $card['match_link']['url'], ' / ', $card['match_link']['label'], "\n";
var_dump($card['can_retry'], $card['dismiss']);

$failed = Found_post_card::from(['title' => '', 'status' => 'failed', 'score' => null, 'tag' => null, 'error' => 'No job post on the page.', 'cv_match_id' => null] + $matched);
echo $failed['heading'], "\n", $failed['status_name'], "\n", $failed['error'], "\n";
var_dump($failed['score'], $failed['match_link'], $failed['can_retry']);

$dismissed = Found_post_card::from(['status' => 'dismissed', 'error' => 'No job post on the page.'] + $matched);
var_dump($dismissed['score'], $dismissed['error'], $dismissed['dismiss']);
?>
--EXPECT--
Backend-udvikler
Nordisk Fragt · found 21 Oct 08:12
81 Good match
cv_match/7 / Open the match and tailored résumé
bool(false)
array(2) {
  ["label"]=>
  string(7) "Dismiss"
  ["fields"]=>
  array(0) {
  }
}
https://jobnet.dk/1
Failed
No job post on the page.
NULL
NULL
bool(true)
NULL
string(0) ""
array(2) {
  ["label"]=>
  string(10) "Bring back"
  ["fields"]=>
  array(1) {
    ["undo"]=>
    string(1) "1"
  }
}
