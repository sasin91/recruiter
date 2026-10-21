--TEST--
The extension page's privacy policy names every site and permission the extension's manifest asks for
--FILE--
<?php
$manifest = json_decode(file_get_contents(__DIR__ . '/../../../extensions/jobnet-joblog/manifest.json'), true);
$page = file_get_contents(__DIR__ . '/../views/index.php');
$policy = substr($page, strpos($page, 'id="privacy"'));

$patterns = $manifest['host_permissions'];
foreach ($manifest['content_scripts'] as $script) {
    array_push($patterns, ...$script['matches']);
}
$asks = array_map(fn(string $pattern) => preg_replace('/^\*\./', '', parse_url($pattern, PHP_URL_HOST)), $patterns);
array_push($asks, ...$manifest['permissions']);

foreach (array_unique($asks) as $ask) {
    echo $ask, ': ', str_contains($policy, $ask) ? 'named' : 'MISSING', "\n";
}
echo 'collects: ', implode(', ', $manifest['browser_specific_settings']['gecko']['data_collection_permissions']['required']), "\n";
?>
--EXPECT--
recruiter.trongate.dev: named
cvrapi.dk: named
jobnet.dk: named
storage: named
collects: none
