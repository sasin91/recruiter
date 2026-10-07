<?php
/**
 * Builds the static taxonomy index Taxonomy ranks against, from the seed in
 * db/taxonomy.sql:
 *
 *   index.json      { model, dim, vectors, vectors_sha256, terms: [{ id, kind,
 *                     parent_id, labels: [{ text, lang, preferred }], definition? }] }
 *   index.q8.bin    one int8 vector per term, in `terms` order:
 *                   [rows x f32 scale][rows x dim x i8], little-endian
 *
 * A term's vector is the mean of its label vectors (preferred labels count
 * double), normalised, so "Projektleder" and "Project manager" both pull the
 * term towards themselves. Vectors are built with the same int8 model the
 * lookup encodes queries with, so the query and the index share one space.
 *
 *   php taxonomy/php/build-index.php [seed.sql]
 *
 * Once the database is the source of truth (labels added through the review
 * queue), the same steps run over the `terms` / `term_labels` tables instead.
 */
require_once __DIR__ . '/Static_model.php';

$root = dirname(__DIR__);
$model_dir = "$root/models/potion-multilingual-da";
$data_dir = "$root/data";
$seed = $argv[1] ?? dirname($root) . '/db/taxonomy.sql';

$meta = json_decode(file_get_contents("$model_dir/model.json"), true, 512, JSON_THROW_ON_ERROR);
$model = Static_model::load($model_dir);
$tables = parse_sql_inserts(file_get_contents($seed));

$terms = [];
foreach ($tables['terms'] as $t) {
    $term = ['id' => $t['id'], 'kind' => $t['kind'], 'parent_id' => $t['parent_id'], 'labels' => []];
    if ($t['definition'] !== null && $t['definition'] !== '') {
        $term['definition'] = $t['definition'];
    }
    $terms[$t['id']] = $term;
}
foreach ($tables['term_labels'] as $l) {
    $terms[$l['term_id']]['labels'][] = ['text' => $l['label'], 'lang' => $l['lang'], 'preferred' => $l['is_preferred'] === 1];
}
$terms = array_values($terms);

$dim = $model->dim;
$scales = '';
$weights = '';
$empty = 0;
foreach ($terms as $term) {
    $sum = array_fill(0, $dim, 0.0);
    foreach ($term['labels'] as $label) {
        $v = $model->encode($label['text'])['vector'];
        if ($v === null) {
            continue;
        }
        $w = $label['preferred'] ? 2 : 1;
        foreach ($v as $i => $x) {
            $sum[$i] += $w * $x;
        }
    }
    $v = Static_model::unit($sum);
    $max = max(array_map('abs', $v));
    if ($max == 0) {
        $empty++;
    }
    $scale = $max / 127 ?: 1;
    $scales .= pack('g', $scale);
    $weights .= pack('c*', ...array_map(fn(float $x) => (int) round($x / $scale), $v));
}

$bin = $scales . $weights;
$vectors = 'index.q8.bin';
file_put_contents("$data_dir/$vectors", $bin);
$index = [
    'model' => $meta['embeddings_sha256'],
    'dim' => $dim,
    'vectors' => $vectors,
    'vectors_sha256' => hash('sha256', $bin),
    'terms' => $terms,
];
file_put_contents("$data_dir/index.json", json_encode($index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS | JSON_THROW_ON_ERROR) . "\n");
printf("%d terms, %d dims, %d without a vector; index.q8.bin %d B\n", count($terms), $dim, $empty, strlen($bin));

/**
 * Rows of the `INSERT IGNORE INTO ... VALUES` statements the seed is made of,
 * one row per line, by table.
 *
 * @return array<string, array<int, array<string, mixed>>>
 */
function parse_sql_inserts(string $sql): array {
    $tables = [];
    $table = null;
    $columns = [];
    foreach (explode("\n", $sql) as $line) {
        if (preg_match('/^INSERT IGNORE INTO `(\w+)` \((.*)\) VALUES$/', $line, $m)) {
            $table = $m[1];
            $columns = array_map(fn(string $c) => trim($c, '`'), explode(', ', $m[2]));
            $tables[$table] ??= [];
            continue;
        }
        if ($table === null || !str_starts_with($line, '(')) {
            $table = null;
            continue;
        }
        $body = substr(preg_replace('/\)[,;]$/', '', $line), 1);
        preg_match_all("/\\s*('(?:[^'\\\\]|''|\\\\.)*'|[^,]*)\\s*(?:,|$)/", $body, $m);
        $values = [];
        foreach (array_slice($m[1], 0, count($columns)) as $value) {
            if (str_starts_with($value, "'")) {
                $values[] = strtr(substr($value, 1, -1), ["''" => "'", '\\n' => "\n", '\\r' => "\r", "\\'" => "'", '\\\\' => '\\']);
            } elseif ($value === 'NULL') {
                $values[] = null;
            } else {
                $values[] = $value + 0;
            }
        }
        $tables[$table][] = array_combine($columns, $values);
    }
    return $tables;
}
