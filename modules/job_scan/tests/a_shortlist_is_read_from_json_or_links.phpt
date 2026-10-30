--TEST--
Job_search_rules::shortlist reads a pasted shortlist as JSON, in a code fence or not, or as text with links
--FILE--
<?php
require __DIR__ . '/../Job_search_rules.php';
$urls = fn(string $text) => print(json_encode(array_column(Job_search_rules::shortlist($text), 'url'), JSON_UNESCAPED_SLASHES) . "\n");

$posts = Job_search_rules::shortlist('[{"url": "https://a.test/1", "title": "Dev", "company": "A"}, {"title": "no url"}, 7]');
echo count($posts), ' ', $posts[0]['title'], ' ', $posts[0]['company'], "\n";
$urls("```json\n[{\"url\": \"https://a.test/1\"}]\n```");
$urls('{"posts": [{"url": "https://a.test/2"}]}');
$urls('["https://a.test/3", "https://a.test/4"]');
$urls("https://a.test/5\nhttps://a.test/6, and (https://a.test/7).\nhttps://a.test/5");
$urls('[{"url": "https://a.test/8", broken');
$urls('no links here');
$urls('');
?>
--EXPECT--
1 Dev A
["https://a.test/1"]
["https://a.test/2"]
["https://a.test/3","https://a.test/4"]
["https://a.test/5","https://a.test/6","https://a.test/7"]
["https://a.test/8"]
[]
[]
