--TEST--
a job search's page summary and its card in the list
--FILE--
<?php
require_once __DIR__ . '/../Job_search_summary.php';
require_once __DIR__ . '/../Job_search_card.php';
$at = (string) mktime(8, 12, 0, 10, 21, 2026);
$search = ['id' => '2', 'name' => 'PHP', 'keywords' => 'php developer', 'location' => 'Odense', 'remote_ok' => '1', 'sites' => ['jobnet.dk', 'jobindex.dk'],
    'scan_every_hours' => '24', 'tailor_from_score' => '60', 'last_scanned_at' => $at, 'active' => '1', 'paused_reason' => '', 'posts' => '12', 'matched' => '4'];
$tokens = ['scans' => '3', 'input_tokens' => '12000', 'output_tokens' => '345'];
$run = ['started_at' => $at, 'finished_at' => $at, 'error' => '', 'posts_found' => '12', 'posts_new' => '3'];

$summary = Job_search_summary::from($search, $run, $tokens);
echo $summary['line'], "\n", implode(' | ', $summary['last_scan']), "\n";
echo Job_search_summary::from(['location' => '', 'remote_ok' => '0', 'sites' => [], 'scan_every_hours' => '36', 'last_scanned_at' => null] + $search, null, $tokens)['line'], "\n";
echo implode(' | ', Job_search_summary::from($search, ['finished_at' => null] + $run, $tokens)['last_scan']), "\n";
echo implode(' | ', Job_search_summary::from($search, ['error' => 'The key was rejected.'] + $run, $tokens)['last_scan']), "\n";

$card = Job_search_card::from($search);
echo $card['meta'], "\n";
var_dump($card['chip'], Job_search_card::from(['active' => '0'] + $search)['chip']['label'], Job_search_card::from(['paused_reason' => 'Key rejected.'] + $search)['chip']['label']);
?>
--EXPECT--
php developer · Odense · remote too · on jobnet.dk, jobindex.dk · Every day · tailors from 60 · last scanned 21 Oct 2026 08:12
21 Oct 08:12 | done | found 12, 3 new. | Scans in the last 30 days: 3, 12,345 tokens.
php developer · 36 h · tailors from 60 · not scanned yet
21 Oct 08:12 | running | running… | Scans in the last 30 days: 3, 12,345 tokens.
21 Oct 08:12 | error | The key was rejected. | Scans in the last 30 days: 3, 12,345 tokens.
php developer · Odense · Every day · 12 found, 4 matched · last scanned 21 Oct 08:12
NULL
string(3) "Off"
string(6) "Paused"
