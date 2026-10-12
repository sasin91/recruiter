--TEST--
generic filler leans to slop and specific sentences to substance
--FILE--
<?php
require __DIR__ . '/setup.inc';
$index = Slop_index::load();
$model = Static_model::load(dirname(__DIR__, 3) . '/taxonomy/models/potion-multilingual-da');
$filler = [
    "Whether you're a beginner or an expert, these tips will help you level up.",
    'Ultimately, success comes down to consistency and a growth mindset.',
    'AI is transforming every aspect of our lives in profound ways.',
];
$specific = [
    'Debugging success with git bisect rose from 19.2% to 81.7%.',
    "PHP's json_decode returns null on invalid input unless you pass JSON_THROW_ON_ERROR.",
    'The town has 4,000 residents and one school, which closed in 2015.',
];
foreach ($filler as $s) {
    var_dump($index->lean($model->encode($s)['vector']) >= Slop_index::FILLER_MARGIN);
}
foreach ($specific as $s) {
    var_dump($index->lean($model->encode($s)['vector']) >= Slop_index::FILLER_MARGIN);
}
?>
--EXPECT--
bool(true)
bool(true)
bool(true)
bool(false)
bool(false)
bool(false)
