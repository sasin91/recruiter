--TEST--
an answer without the scores is an error
--FILE--
<?php
require __DIR__ . '/setup.inc';
try {
    Laya_client::read_answer(['answers' => ['meets' => ['noul' => 0.5]]]);
    echo "read\n";
} catch (RuntimeException $e) {
    echo get_class($e), "\n";
}
?>
--EXPECT--
RuntimeException
