--TEST--
pages in other encodings come out as UTF-8
--FILE--
<?php
require __DIR__ . '/setup.inc';
$latin1 = mb_convert_encoding('<html><body><p>Søger dygtig udvikler i Århus</p></body></html>', 'ISO-8859-1', 'UTF-8');
var_dump(Page_reader::to_text($latin1, 'text/html; charset=iso-8859-1')['text']);
var_dump(Page_reader::to_text("Plain  text\r\n\r\n\r\npost", 'text/plain')['text']);
?>
--EXPECT--
string(31) "Søger dygtig udvikler i Århus"
string(16) "Plain text

post"
