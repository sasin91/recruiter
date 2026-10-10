--TEST--
_enqueue('_score', [42]): without a module it's the caller's, and positional arguments get their parameter names
--FILE--
<?php
// A stand-in for the engine: Trongate's module_name, and APPPATH with one module.
class Trongate {
    protected ?string $module_name = '';

    public function __construct(?string $module_name = null) {
        $this->module_name = $module_name ?? strtolower(get_class($this));
    }
}
$app = sys_get_temp_dir() . '/queue-test-' . getmypid() . '/';
@mkdir($app . 'modules/notes', 0777, true);
file_put_contents($app . 'modules/notes/Notes.php', <<<'PHP'
<?php
class Notes extends Trongate {
    public function _save(string $text, int $times = 1, string ...$tags): void {
    }

    public function call_queue(Queue $queue, string $method): string {
        return (fn() => $this->calling_module($method))->call($queue);
    }
}
PHP);
define('APPPATH', $app);
require __DIR__ . '/../Queue.php';
require $app . 'modules/notes/Notes.php';

$queue = new Queue('queue');
$named = fn(...$args) => (new ReflectionMethod('Queue', 'named'))->invoke(null, ...$args);

echo (new Notes('notes'))->call_queue($queue, '_save'), "\n";
try {
    (fn() => $this->calling_module('_save'))->call($queue);
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(), "\n";
}

echo json_encode($named('notes', '_save', ['hi'])), "\n";
echo json_encode($named('notes', '_save', ['hi', 3])), "\n";
echo json_encode($named('notes', '_save', ['hi', 'times' => 3])), "\n";
echo json_encode($named('notes', '_save', ['times' => 3, 'text' => 'hi'])), "\n";
echo json_encode($named('notes', '_nope', [1])), "\n";
foreach ([['hi', 3, 'x'], ['hi', 'text' => 'again']] as $args) {
    try {
        $named('notes', '_save', $args);
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
unlink($app . 'modules/notes/Notes.php');
rmdir($app . 'modules/notes');
rmdir($app . 'modules');
rmdir($app);
?>
--EXPECT--
notes
Say which module _save is on: it isn't called from a module.
{"text":"hi"}
{"text":"hi","times":3}
{"text":"hi","times":3}
{"times":3,"text":"hi"}
[1]
notes/_save takes 3 argument(s) by position; pass the rest by name.
notes/_save gets $text twice.
