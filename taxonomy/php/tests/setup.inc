<?php
require_once __DIR__ . '/../Taxonomy.php';

$root = dirname(__DIR__, 2);
$model_dir = "$root/models/potion-multilingual-da";
$model = Static_model::load($model_dir);

// ---------- tokenizer and model ----------

$decomposed = "a\u{030A}rsag";
$composed = "\u{00E5}rsag";

// ---------- lexical ----------

// ---------- ranking against a tiny index ----------

$terms = [
    ['id' => 1, 'kind' => 'skill', 'labels' => [['text' => 'SAP', 'lang' => 'da', 'preferred' => true], ['text' => 'SAP', 'lang' => 'en', 'preferred' => true]]],
    ['id' => 2, 'kind' => 'title', 'labels' => [['text' => 'Projektleder', 'lang' => 'da', 'preferred' => true], ['text' => 'Project manager', 'lang' => 'en', 'preferred' => true]]],
    ['id' => 3, 'kind' => 'skill', 'labels' => [['text' => 'Kørekort B', 'lang' => 'da', 'preferred' => true], ['text' => 'B-kørekort', 'lang' => 'da', 'preferred' => false]]],
    ['id' => 4, 'kind' => 'skill', 'labels' => [['text' => 'Regnskab', 'lang' => 'da', 'preferred' => true], ['text' => 'Accounting', 'lang' => 'en', 'preferred' => true]]],
];
$scales = '';
$weights = '';
foreach ($terms as $term) {
    $v = $model->encode($term['labels'][0]['text'])['vector'];
    $scale = max(array_map('abs', $v)) / 127;
    $scales .= pack('g', $scale);
    $weights .= pack('c*', ...array_map(fn(float $x) => (int) round($x / $scale), $v));
}
$tiny = new Taxonomy(['dim' => $model->dim, 'terms' => $terms], $scales . $weights, $model);
$top = fn(string $query, ?string $kind = null) => $tiny->search($query, $kind, 1)[0];

// ---------- the built index ----------

$built = Taxonomy::load();
$preferred_da = function (array $term): ?string {
    foreach ($term['labels'] as $label) {
        if ($label['lang'] === 'da' && $label['preferred']) {
            return $label['text'];
        }
    }
    return null;
};
