<?php
// The adapters are Trongate modules; these stand-ins let them load alone.
if (!class_exists('Trongate')) {
    class Trongate {
        public ?string $parent_module = null;
        public function __construct(?string $module_name = null) {}
    }
}
if (!function_exists('block_url')) {
    function block_url(string $url): void {}
}
require_once __DIR__ . '/../Llm.php';
require_once __DIR__ . '/../openai/Openai.php';

/** A local OpenAI stand-in that waits `seconds` before answering. */
function slow_server(int $seconds): array {
    $dir = sys_get_temp_dir() . '/llm-slow-' . getmypid();
    @mkdir($dir);
    file_put_contents("$dir/index.php", "<?php sleep($seconds); echo '{}';");
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
    fclose($socket);
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $dir], [['pipe', 'r'], ['file', $null, 'w'], ['file', $null, 'w']], $pipes);
    for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
        usleep(100000);
    }
    return [$server, "http://127.0.0.1:$port"];
}
