--TEST--
the free reader finds skills a post names, nice-to-haves and years
--FILE--
<?php
require __DIR__ . '/setup.inc';
$reader = new Free_reader($taxonomy);
$job = $reader->job(<<<TEXT
    Backend-udvikler
    Om jobbet
    Du bliver en del af et team med gode kolleger.
    Din profil
    - 3+ års erfaring med PHP og Laravel
    - Kendskab til Kubernetes er en fordel
    - Du er struktureret
    Vi tilbyder
    - Pension og sundhedsforsikring
    TEXT);
$by_value = array_column($job['requirements'], null, 'value');
var_dump($by_value['PHP']['required']);
var_dump($by_value['Laravel']['kind']);
var_dump($by_value['Kubernetes']['required']);
var_dump($by_value['3 års erfaring']['min_years']);
var_dump($by_value['Struktureret']['kind'] ?? null);
echo 'what the post offers is not asked for: ';
var_dump(isset($by_value['Pension']));

$profile = $reader->cv("Udvikler\n2018 - nu: PHP, Laravel og Kubernetes\nUddannelse\n2014 - 2016 Datamatiker");
$matcher = new Cv_matcher($taxonomy, $profile, $reader->known);
$groups = Cv_matcher::criteria($job);
$verdicts = [];
foreach (Cv_matcher::units($groups) as $unit) {
    $verdicts[$unit['text']] = $matcher->decide($unit)['verdict'] ?? 'missing';
}
var_dump($verdicts['PHP']);
var_dump($verdicts['Kubernetes']);
var_dump($verdicts['3 års erfaring']);
?>
--EXPECT--
bool(true)
string(5) "skill"
bool(false)
int(3)
string(10) "soft_skill"
what the post offers is not asked for: bool(false)
string(3) "met"
string(3) "met"
string(3) "met"
