--TEST--
a page listing several JobPostings gives its own, not the first
--FILE--
<?php
require __DIR__ . '/setup.inc';
$posting = fn(string $title, string $url) => [
    '@type' => 'JobPosting',
    'title' => $title,
    'url' => $url,
    'hiringOrganization' => ['name' => "$title A/S"],
    'description' => str_repeat("<p>$title: du har erfaring med PHP og Laravel.</p>", 8),
];
$json = json_encode(['@graph' => [
    $posting('Backendudvikler', 'https://jobs.example.com/job/1/backend'),
    $posting('Dataingeniør', 'https://jobs.example.com/job/2/data'),
    $posting('Frontendudvikler', 'https://jobs.example.com/job/3/frontend'),
]]);
$html = "<html><head><title>Job</title><script type=\"application/ld+json\">$json</script></head><body><main><p>Side</p></main></body></html>";
var_dump(Page_reader::to_text($html, 'text/html', 'https://jobs.example.com/job/2/data/')['title']);
var_dump(Page_reader::to_text($html, 'text/html', 'https://jobs.example.com/job/3/frontend')['title']);
// None of them is this page's: the page text, not a guess.
var_dump(Page_reader::to_text($html, 'text/html', 'https://jobs.example.com/job/9/other')['text']);
// Without urls, the one named in the page's <title>.
$plain = json_encode([
    array_diff_key($posting('Backendudvikler', ''), ['url' => 1]),
    array_diff_key($posting('Dataingeniør', ''), ['url' => 1]),
]);
var_dump(Page_reader::to_text("<html><head><title>Dataingeniør hos Firma</title><script type=\"application/ld+json\">$plain</script></head><body><p>x</p></body></html>")['title']);
?>
--EXPECT--
string(13) "Dataingeniør"
string(16) "Frontendudvikler"
string(4) "Side"
string(13) "Dataingeniør"
