--TEST--
a written résumé comes back as its box with the keywords it took over, and its own match next to the CV's
--FILE--
<?php
error_reporting(E_ALL);
set_error_handler(function (int $level, string $message) { throw new ErrorException($message, 0, $level); });
// out() as Trongate's string_service gives it for html.
function out(?string $input): string {
    return htmlspecialchars((string) $input, ENT_QUOTES, 'UTF-8');
}
require __DIR__ . '/../Document_box.php';
require __DIR__ . '/../Resume_comparison.php';
$box = Document_box::from('resume', 7, ['text' => 'Jonas <dev>', 'written_at' => '1760000000', 'structured' => true], true, null);
var_dump($box['keywords'], $box['left_out']);
$box = Document_box::with_keywords($box, [['term' => 'PHP', 'basis' => '9 years of PHP'], ['term' => 'Go & Rust', 'basis' => '']], ['Kotlin', 'Swift']);
var_dump($box['keywords']['summary'], $box['left_out']);
ob_start();
require __DIR__ . '/../views/document_box.php';
$html = ob_get_clean();
foreach (['data-kind="resume" data-id="7"', 'data-written-at="1760000000"', 'data-structured="true"', '>Write it again<', '>Jonas &lt;dev&gt;</textarea>',
        '<strong>PHP</strong>: from 9 years of PHP</li>', '<strong>Go &amp; Rust</strong></li>', 'doesn&#039;t show it: Kotlin, Swift'] as $part) {
    echo $part, ': ', str_contains($html, $part) ? 'yes' : 'no', "\n";
}
$plain = Document_box::with_keywords(Document_box::from('application', 7, [], true, null), [], []);
ob_start();
$box = $plain;
require __DIR__ . '/../views/document_box.php';
$plain_html = ob_get_clean();
echo 'no keywords box: ', str_contains($plain_html, 'class="keywords"') ? 'yes' : 'no', "\n";

$comparison = Resume_comparison::from(['id' => '7', 'score' => '0.62', 'tag' => 'medium'], ['id' => '12', 'score' => '0.814', 'tag' => 'good']);
var_dump($comparison);
ob_start();
require __DIR__ . '/../views/resume_comparison.php';
echo trim(ob_get_clean()), "\n";
?>
--EXPECT--
NULL
string(0) ""
string(45) "Uses 2 of the post's keywords: PHP, Go & Rust"
string(54) "Left out, since your CV doesn't show it: Kotlin, Swift"
data-kind="resume" data-id="7": yes
data-written-at="1760000000": yes
data-structured="true": yes
>Write it again<: yes
>Jonas &lt;dev&gt;</textarea>: yes
<strong>PHP</strong>: from 9 years of PHP</li>: yes
<strong>Go &amp; Rust</strong></li>: yes
doesn&#039;t show it: Kotlin, Swift: yes
no keywords box: no
array(2) {
  ["line"]=>
  string(83) "The tailored résumé scores 81% good against this post; your CV scored 62% medium."
  ["open_id"]=>
  int(12)
}
The tailored résumé scores 81% good against this post; your CV scored 62% medium. Saved as its own match. <button type="button" class="link-button open-saved" data-id="12">Open it</button>
