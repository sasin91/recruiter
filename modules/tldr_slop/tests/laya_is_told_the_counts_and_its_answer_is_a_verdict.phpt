--TEST--
Laya is told the counts in English, asked a yes/no and a 0-4 score, and its answer becomes a verdict
--FILE--
<?php
require __DIR__ . '/setup.inc';
echo Slop_index::laya_summary('Faster imports', [
    'sentences' => 20, 'words' => 400, 'filler' => 1, 'repeats' => 0, 'figures' => 9,
    'code_blocks' => 1, 'links' => 3, 'stock_words' => 3,
], ['robust' => 2, 'leverage' => 1]), "\n";
echo Slop_index::laya_summary('', [
    'sentences' => 1, 'words' => 12, 'filler' => 1, 'repeats' => 1, 'figures' => 1,
    'code_blocks' => 0, 'links' => 1, 'stock_words' => 0,
], []), "\n";
$questions = Slop_index::questions();
var_dump($questions['slop']['type'], $questions['substance']['type'], count($questions['substance']['criteria']));
print_r(Slop_index::read_answer(['slop' => ['noul' => 0.81234], 'substance' => ['score' => 0.9]]));
print_r(Slop_index::read_answer(['slop' => ['noul' => 0.2], 'substance' => ['score' => 5.2]]));
try {
    Slop_index::read_answer(['slop' => ['noul' => 0.2]]);
} catch (RuntimeException $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Article: Faster imports. 400 words in 20 sentences. 1 of 20 sentences (5%) read like generic filler. 0 sentences repeat an earlier point. 9 sentences (45%) give a figure. It has 1 code block and 3 links to other sites. Stock AI words: robust (2), leverage.
Article: untitled. 12 words in 1 sentences. 1 of 1 sentences (100%) read like generic filler. 1 sentence repeats an earlier point. 1 sentence (100%) gives a figure. It has 0 code blocks and 1 link to other sites. Stock AI words: none.
string(4) "noul"
string(5) "score"
int(5)
Array
(
    [slop] => 0.8123
    [substance] => 0.9
    [substance_label] => very little
    [verdict] => slop
)
Array
(
    [slop] => 0.2
    [substance] => 4
    [substance_label] => dense with specifics
    [verdict] => not slop
)
Laya answered without the slop and substance scores.
