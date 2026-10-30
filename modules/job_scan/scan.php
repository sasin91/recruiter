<?php
/**
 * The job scan from the command line, by hand or from Windows Task Scheduler
 * or cron (README.md), so no worker has to be running all the time:
 *
 *   php modules/job_scan/scan.php                     scan the searches that are due
 *   php modules/job_scan/scan.php --search=3          scan this search now, due or not
 *   php modules/job_scan/scan.php --search=3 --add=shortlist.json
 *                                                     add a shortlist (a file, or - for stdin), no scan
 *
 * Then it runs what is queued (the scans, the matches of the posts they find)
 * until nothing is due, and exits. With no worker running, the queue runs a
 * job when it is queued, so a failed one isn't retried: its post is marked
 * failed, and a failed scan is in the search's runs. Loads the app like
 * queue/runtime.php, but leaves session cookies on: ignition.php warns otherwise.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__, 2);
$_SERVER += ['HTTP_HOST' => 'localhost', 'REQUEST_URI' => '/', 'REQUEST_METHOD' => 'GET'];
ini_set('session.save_handler', 'files');
ini_set('session.save_path', sys_get_temp_dir());
chdir($root . '/public');
require $root . '/engine/ignition.php';
require_once __DIR__ . '/../queue/Queue_console.php';
require_once __DIR__ . '/Job_scan.php';

$log = fn(string $line) => fwrite(STDERR, date('[Y-m-d H:i:s] ') . $line . "\n");
[, , $options] = Queue_console::parse(array_merge(['scan'], array_slice($argv, 1)));
$search_id = (int) ($options['search'] ?? 0);
$scan = new Job_scan('job_scan');

try {
    $runtime = Queue::_runtime($log);
    if (isset($options['add'])) {
        if ($search_id < 1) {
            $log('--add needs --search=ID, the search to add the shortlist to.');
            exit(2);
        }
        $file = $options['add'] === '-' ? 'php://stdin' : (string) $options['add'];
        $text = $options['add'] === true ? false : @file_get_contents($file);
        if ($text === false) {
            $log("Can't read the shortlist from $file.");
            exit(2);
        }
        $added = $scan->_add_shortlist($search_id, Job_search_rules::shortlist($text));
        if ($added === null) {
            $log("There is no job search $search_id.");
            exit(1);
        }
        $log(count($added['added']) . ' added, ' . count($added['skipped']) . ' skipped.');
    } elseif (isset($options['search'])) {
        $problem = $search_id > 0 ? $scan->_queue_scan($search_id) : '--search needs the id of a search.';
        if ($problem !== null) {
            $log($problem);
            exit(1);
        }
    } else {
        $log(count($scan->_queue_due()) . ' searches are due a scan.');
    }
    exit((new Queue_console($runtime))->run(['work', '--stop-when-empty']));
} catch (Throwable $e) {
    $log('Job scan: ' . $e->getMessage());
    exit(1);
}
