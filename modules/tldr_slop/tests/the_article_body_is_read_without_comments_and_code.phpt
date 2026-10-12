--TEST--
the article body is read without comments and code, which is counted with outside links
--FILE--
<?php
require __DIR__ . '/setup.inc';
$filler = str_repeat('This sentence is only here to make the article body long enough to count. ', 8);
$html = <<<HTML
<html><head><title>Site name</title><meta property="og:title" content="How we fixed the import"></head>
<body><nav>Home About Contact us today now</nav>
<main><div id="article-body">
<p>We moved the import to a staging table. It now takes nine minutes!</p>
<pre><code>LOAD DATA INFILE 'x.csv' INTO TABLE staging;</code></pre>
<p>See <a href="https://mariadb.com/kb/">the manual</a> and <a href="/tags/sql">our tag</a>. $filler</p>
<p>Too short.</p>
</div>
<section id="comments"><p>Great article, thanks so much for sharing this with us!</p></section>
</main></body></html>
HTML;
$article = Article_reader::parse($html, 'text/html', 'https://dev.to/someone/post');
var_dump($article['title']);
var_dump($article['code_blocks'], $article['links']);
var_dump($article['sentences'][0], $article['sentences'][1]);
var_dump(count(array_filter($article['sentences'], fn($s) => str_contains($s, 'LOAD DATA') || str_contains($s, 'Great article'))));
?>
--EXPECT--
string(23) "How we fixed the import"
int(1)
int(1)
string(39) "We moved the import to a staging table."
string(26) "It now takes nine minutes!"
int(0)
