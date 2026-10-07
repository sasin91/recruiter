<?php
/**
 * Tests for the taxonomy lookup. Run with: php taxonomy/php/tests.php
 *
 * No test framework, just assertions; exits non-zero if any test fails.
 * The last group ranks real Danish phrases against the
 * built index in taxonomy/data.
 */
require_once __DIR__ . '/Taxonomy.php';

$root = dirname(__DIR__);
$model_dir = "$root/models/potion-multilingual-da";
$model = Static_model::load($model_dir);
$failed = 0;

function test(string $name, callable $body): void {
    global $failed;
    try {
        $body();
        echo "ok   $name\n";
    } catch (Throwable $e) {
        $failed++;
        echo "FAIL $name\n     {$e->getMessage()} (line {$e->getLine()})\n";
    }
}

function check(bool $condition, string $message = 'assertion failed'): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function same(mixed $actual, mixed $expected, string $message = ''): void {
    check($actual === $expected, trim("$message expected " . json_encode($expected, JSON_UNESCAPED_UNICODE) . ', got ' . json_encode($actual, JSON_UNESCAPED_UNICODE)));
}

// ---------- tokenizer and model ----------

test('tokenizes exactly like the reference Hugging Face tokenizer', function () use ($model, $model_dir) {
    // Produced by @huggingface/tokenizers from the unpruned model's tokenizer.json.
    $cases = json_decode(file_get_contents("$model_dir/tokenizer-fixture.json"), true);
    check(count($cases) >= 20);
    foreach ($cases as $case) {
        same($model->tokenize($case['text']), $case['tokens'], json_encode($case['text'], JSON_UNESCAPED_UNICODE));
    }
});

$decomposed = "a\u{030A}rsag";
$composed = "\u{00E5}rsag";

test('keeps æ, ø and å through normalisation', function () use ($decomposed, $composed) {
    same(Static_model::prepare('Ærlighed på færøsk'), "\u{2581}Ærlighed\u{2581}på\u{2581}færøsk");
    // A decomposed å (a + combining ring) is composed, not stripped.
    same(Static_model::prepare($decomposed), "\u{2581}" . $composed);
    same(Taxonomy::words('Kørekort B, Århus'), ['kørekort', 'b', 'århus']);
    same(Taxonomy::words($decomposed), [$composed]);
});

test("encodes to a unit vector of the model's dimension", function () use ($model) {
    $e = $model->encode('Projektleder med erfaring i byggeriet');
    same(count($e['vector']), $model->dim);
    check(abs(Static_model::cosine($e['vector'], $e['vector']) - 1) < 1e-5);
    same($e['unknown'], 0);
});

test('text with no known tokens has no vector', function () use ($model) {
    same($model->encode('')['vector'], null);
});

test('folds capitalised words but keeps acronyms and mixed case', function () use ($model) {
    same(Static_model::fold_case('Regnskab og Økonomi'), 'regnskab og økonomi');
    same(Static_model::fold_case('SAP, IT-Chef, iOS, JavaScript, PowerPoint'), 'SAP, IT-chef, iOS, JavaScript, PowerPoint');
    $a = $model->encode('Regnskab')['vector'];
    $b = $model->encode('regnskab')['vector'];
    check(Static_model::cosine($a, $b) > 0.99);
});

test('Danish and English names of one thing are close, unrelated things are not', function () use ($model) {
    $close = Static_model::cosine($model->encode('Projektleder')['vector'], $model->encode('Project manager')['vector']);
    $far = Static_model::cosine($model->encode('Projektleder')['vector'], $model->encode('Sygeplejerske')['vector']);
    check($close > $far + 0.2, "close $close, far $far");
});

// ---------- lexical ----------

test("drops Danish and English stopwords and job-ad qualifiers, never 'it'", function () {
    same(Taxonomy::content_words('Erfaring med SAP og Excel'), ['sap', 'excel']);
    same(Taxonomy::content_words('strong knowledge of IT'), ['it']);
    same(Taxonomy::content_words('Kendskab til IT-support'), ['it', 'support']);
});

test('edit distance counts swaps as one edit and gives up early', function () {
    same(Taxonomy::edit_distance('projektleder', 'projektleder'), 0);
    same(Taxonomy::edit_distance('projektleder', 'projketleder'), 1);
    same(Taxonomy::edit_distance('projektleder', 'projektleeder'), 1);
    same(Taxonomy::edit_distance('ærlig', 'aerlig', 2), 2);
    same(Taxonomy::edit_distance('sap', 'salgschef', 2), 3);
    same(Taxonomy::typo_budget('sap'), 0);
    same(Taxonomy::typo_budget('excel'), 1);
    same(Taxonomy::typo_budget('projektleder'), 2);
});

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

test('an exact name inside a longer phrase wins and reports exact', function () use ($top) {
    $r = $top('Erfaring med SAP');
    same($r['term']['id'], 1);
    same(Taxonomy::match_method($r, 'Erfaring med SAP'), 'exact');
});

test('a typo still finds the term and reports fuzzy', function () use ($top) {
    $r = $top('projektleeder');
    same($r['term']['id'], 2);
    same(Taxonomy::match_method($r, 'projektleeder'), 'fuzzy');
});

test('an English query finds the Danish term', function () use ($top) {
    same($top('Project manager')['term']['id'], 2);
    same($top('accounting', 'skill')['term']['id'], 4);
});

test('a non-preferred label reports synonym', function () use ($top) {
    $r = $top('B-kørekort');
    same($r['term']['id'], 3);
    same(Taxonomy::match_method($r, 'B-kørekort'), 'synonym');
});

test('kind narrows the result', function () use ($tiny) {
    $results = $tiny->search('Projektleder', 'skill', 5);
    check(count($results) > 0);
    foreach ($results as $r) {
        same($r['term']['kind'], 'skill');
    }
});

test('ranks lexically when there is no vector', function () use ($tiny) {
    $r = $tiny->rank('regnskab', null, null, 1)[0];
    same($r['term']['id'], 4);
    same($r['semantic'], null);
});

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

test('the built index answers real Danish phrases', function () use ($built, $preferred_da) {
    $cases = [
        ['Erfaring med SAP', 'skill', 'SAP'],
        ['projektleeder', 'title', 'Projektleder'],
        ['Project manager', 'title', 'Projektleder'],
        ['kørekort B', 'skill', 'Kørekort B'],
        ['regnskab', 'skill', 'Regnskab'],
        ['Software developer', 'role', 'Softwareudvikler'],
        ['Excel', 'skill', 'Excel'],
    ];
    foreach ($cases as [$query, $kind, $expected]) {
        $r = $built->search($query, $kind, 1)[0];
        same($preferred_da($r['term']), $expected, $query);
    }
});

test('every term in the index has a Danish preferred label', function () use ($built, $preferred_da) {
    foreach ($built->terms as $term) {
        check($preferred_da($term) !== null, "term {$term['id']}");
    }
});

exit($failed ? 1 : 0);
