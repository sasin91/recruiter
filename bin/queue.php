<?php
/**
 * The queue's command line (modules/queue): run a worker, look at and
 * retry failed jobs.
 *
 *   php bin/queue.php work --time-limit=3600     a worker (Trongate.cloud: the app's worker command)
 *   php bin/queue.php check                         waiting and failed jobs that no longer fit their method
 *   php bin/queue.php stats
 *   php bin/queue.php failed:show [id]
 *   php bin/queue.php failed:retry id ... | --all
 *   php bin/queue.php failed:remove id ...
 *
 * Loads the app as a web request would (engine/ignition.php), so queued
 * jobs run on controllers and models as usual, but with sessions kept in
 * files: no session store is needed and nobody's session is touched.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET'];
ini_set('session.save_handler', 'files');
ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');
chdir($root . '/public');
require $root . '/engine/ignition.php';
require_once $root . '/modules/queue/Queue_console.php';

$log = fn(string $line) => fwrite(STDERR, date('[Y-m-d H:i:s] ') . $line . "\n");
try {
    $runtime = Queue::_runtime($log);
} catch (Throwable $e) {
    fwrite(STDERR, 'Queue: ' . $e->getMessage() . "\n");
    exit(1);
}
exit((new Queue_console($runtime))->run(array_slice($argv, 1)));
