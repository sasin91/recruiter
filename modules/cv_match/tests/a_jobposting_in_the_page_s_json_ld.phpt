--TEST--
a JobPosting in the page's JSON-LD wins
--FILE--
<?php
require __DIR__ . '/setup.inc';
$description = str_repeat('&lt;p&gt;Du har erfaring med PHP og Laravel.&lt;/p&gt;', 8);
$json = json_encode(['@context' => 'https://schema.org', '@graph' => [['@type' => 'WebPage'], [
    '@type' => 'JobPosting',
    'title' => 'Backend-udvikler',
    'hiringOrganization' => ['@type' => 'Organization', 'name' => 'Firma A/S'],
    'jobLocation' => ['@type' => 'Place', 'address' => ['postalCode' => '8000', 'addressLocality' => 'Aarhus C']],
    'description' => html_entity_decode($description),
]]]);
$page = Page_reader::to_text("<html><head><script type=\"application/ld+json\">$json</script></head><body><p>Menu</p></body></html>");
var_dump($page['title']);
var_dump(explode("\n", $page['text'])[0] . '|' . explode("\n", $page['text'])[1] . '|' . explode("\n", $page['text'])[2]);
var_dump(str_contains($page['text'], 'Menu'));
var_dump(str_contains($page['text'], 'Du har erfaring med PHP og Laravel.'));
?>
--EXPECT--
string(16) "Backend-udvikler"
string(40) "Backend-udvikler|Firma A/S|8000 Aarhus C"
bool(false)
bool(true)
