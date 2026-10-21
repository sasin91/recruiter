--TEST--
Store_links keeps a browser's link only when it is https and on that browser's own store
--FILE--
<?php
require __DIR__ . '/../Store_links.php';
var_dump(Store_links::listed([
    'chrome' => 'https://chromewebstore.google.com/detail/joblog-filler/abc',
    'edge' => ' https://microsoftedge.microsoft.com/addons/detail/xyz ',
    'firefox' => 'http://addons.mozilla.org/firefox/addon/joblog-filler/',
]));
// A Chrome link pasted as Firefox's, and nothing set at all.
var_dump(Store_links::listed(['firefox' => 'https://chromewebstore.google.com/detail/abc', 'chrome' => '']));
var_dump(Store_links::listed([]));
// The extension page's list, with Firefox still missing.
echo json_encode(Store_links::for_page(Store_links::listed(['edge' => 'https://microsoftedge.microsoft.com/addons/detail/xyz', 'chrome' => 'https://chromewebstore.google.com/detail/abc']))), "\n";
?>
--EXPECT--
array(2) {
  ["chrome"]=>
  string(58) "https://chromewebstore.google.com/detail/joblog-filler/abc"
  ["edge"]=>
  string(53) "https://microsoftedge.microsoft.com/addons/detail/xyz"
}
array(0) {
}
array(0) {
}
{"stores":[{"name":"Chrome, Brave, Opera, Vivaldi","url":"https:\/\/chromewebstore.google.com\/detail\/abc"},{"name":"Microsoft Edge","url":"https:\/\/microsoftedge.microsoft.com\/addons\/detail\/xyz"}],"some_unlisted":true}
