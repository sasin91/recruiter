--TEST--
a short secret is refused
--FILE--
<?php
require __DIR__ . '/setup.inc';
try {
    new Key_vault('short');
    echo "accepted\n";
} catch (RuntimeException $e) {
    echo get_class($e), "\n";
}
?>
--EXPECT--
RuntimeException
