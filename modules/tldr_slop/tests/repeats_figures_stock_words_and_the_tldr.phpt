--TEST--
an article's repeats, figures, stock words and TL;DR
--FILE--
<?php
require __DIR__ . '/setup.inc';
$sentences = [
    'In today\'s fast-paced digital landscape, staying ahead is more crucial than ever.',
    'Every night we import 2.3 million rows from the supplier catalogue into MariaDB.',
    'The lookup on the SKU column scanned the whole table for every row.',
    'We added a separate index on the SKU column and the import dropped to 48 minutes.',
    'Loading through a staging table brought the nightly import down to nine minutes.',
    'In today\'s fast-paced digital landscape, staying ahead is more crucial than ever!',
];
$reading = Slop_index::load()->assess(['sentences' => $sentences, 'words' => 80, 'code_blocks' => 0, 'links' => 2]);
var_dump($reading['sentences'][0]['filler'], $reading['sentences'][3]['filler']);
var_dump($reading['sentences'][5]['repeats']);
print_r($reading['counts']);
print_r($reading['stock_words']);
var_dump(count($reading['tldr']), in_array($sentences[0], $reading['tldr'], true));
?>
--EXPECT--
bool(true)
bool(false)
int(0)
Array
(
    [sentences] => 6
    [words] => 80
    [filler] => 2
    [repeats] => 1
    [figures] => 2
    [code_blocks] => 0
    [links] => 2
    [stock_words] => 4
)
Array
(
    [landscape] => 2
    [crucial] => 2
)
int(3)
bool(false)
