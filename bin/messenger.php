<?php
/**
 * Messenger's command line (modules/messenger): run a worker, look at and
 * retry failed messages.
 *
 *   php bin/messenger.php consume --time-limit=3600     a worker (Trongate.cloud: the app's worker command)
 *   php bin/messenger.php stats
 *   php bin/messenger.php failed:show [id]
 *   php bin/messenger.php failed:retry id ... | --all
 *   php bin/messenger.php failed:remove id ...
 *
 * Loads the app as a web request would (engine/ignition.php), so handlers
 * can use modules and models, but with sessions kept in files: no session
 * store is needed and nobody's session is touched.
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
require_once $root . '/modules/messenger/Messenger_console.php';

$log = fn(string $line) => fwrite(STDERR, date('[Y-m-d H:i:s] ') . $line . "\n");
try {
    $runtime = Messenger_runtime::from_file(Messenger::connection(), $root . '/config/messenger.php', $log);
} catch (Throwable $e) {
    fwrite(STDERR, 'Messenger: ' . $e->getMessage() . "\n");
    exit(1);
}
exit((new Messenger_console($runtime))->run(array_slice($argv, 1)));
