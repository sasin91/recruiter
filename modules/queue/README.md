# Queue

A job queue for Trongate apps: run a module's method later, in a worker process.

```php
// in modules/applications/Applications.php
$this->queue->_enqueue('_score', [$application_id]);
```

enqueues a **job**: this module's `_score($application_id)`. A **worker** dequeues it and runs it the way a controller calls another module:

```php
$this->module('applications');
$this->applications->_score(application_id: $application_id);
```

When it throws, the worker retries it with exponential backoff, and when it keeps failing, keeps it with its error.

A job's module is what `$this->module()` takes (`'applications'`, or `'parent-child'` for a child module), and its method is a **public** method on that module's controller whose name starts with `_`. Trongate's router never serves those from a URL, so a job's method can't be called from the browser.

The signature is `_enqueue(string|Closure $method, array $arguments = [], ?string $module = null)`. Without `$module`, the method is on the module that calls `_enqueue()` (found with `debug_backtrace()`). Closures aren't supported yet. Arguments are positional or named, as in a normal call. Positional ones are stored under their parameter names, so a job doesn't break when a method's parameters are reordered or one with a default is added. Values are `int`, `float`, `bool`, `string`, `null` or arrays of those, stored as one JSON object in `queue_jobs.parameters`.

The module is self-contained: nothing outside this folder but the Trongate engine, and only `Queue.php` (the controller) needs that. The rest is plain PHP on PDO, so a framework-free worker can use it with its own runner.

## Terms

| Term | Here |
|---|---|
| Job | `Job`: the module, method, parameters, and its state (attempts, reserved, failed, last error). One row in `queue_jobs`. |
| Queue | A named list of jobs (`default` unless `config/queue.php` adds more). `Job_queue` is the interface: `enqueue`, `dequeue`, `ack`, `retry`, `fail`. `Database_job_queue` keeps jobs in MariaDB/MySQL, `In_memory_job_queue` in an array (tests). |
| Dispatcher | `Dispatcher::dispatch()` checks a job and enqueues it on its queue (routing), or runs it at once (`sync`, or no worker running). |
| Worker | `Worker`: dequeues due jobs and runs them, `php modules/queue/runtime.php work`. Each worker writes a heartbeat row in `queue_workers`. |
| Reserved | A dequeued job is reserved by one worker (`reserved_by`) until it is acked, retried or failed. A job reserved longer than the visibility timeout (1 h) belonged to a worker that died, and is handed out again. |
| Retry policy | `Retry_policy`, per queue: 3 retries, 1 s apart and doubling, at most 1 h. A method that knows retrying can't help throws `Unrecoverable_job_exception`. |
| Failed job | Retries used up: the job stays with `failed_at` and the error until retried or removed (`failed:show`, `failed:retry`, `failed:remove`, the admin page `queue/manage`). |
| Unique job | With `unique: true`, a job with the same module, method and parameters isn't enqueued twice while it waits or runs (`unique_key`). A failed one is enqueued again. |

## When a method's signature changes

The parameters are checked against the method with Reflection: every name exists, every required one is given, and each value's type fits.

- **When enqueued:** `_enqueue()` throws `InvalidArgumentException` saying what doesn't fit, so a mistake shows up in the request or a test, not in the worker.
- **Before a job runs:** a job enqueued under an older signature (say, a parameter renamed in a deploy) fails at once with that reason, without retries. It shows on `queue/manage` and in `failed:show`. Once the method fits again, Retry runs it.
- **When a worker starts:** `work` first runs `check`, which logs every waiting or failed job that no longer fits. Run `php modules/queue/runtime.php check` after a deploy to see them (exit code 1 when there are any).

To change a job's method safely, add new parameters with a default, or keep the old name until the jobs enqueued with it have run.

## Setting it up in an app

1. Put this folder at `modules/queue` and add `sql/queue.sql` to the app's schema.
2. Optionally, write `config/queue.php` with queues, retries and routing (see `Queue_runtime`). Without it there is one `default` queue with the default retry policy.
3. Run a worker: `php modules/queue/runtime.php work --time-limit=3600`. Under Kubernetes or systemd, let it exit and be restarted; that keeps memory use and code fresh.

## Using it

```php
// In modules/orders/Orders.php: enqueue it, and say how it went
$job = $this->queue->_enqueue('_send_receipt', [$order_id]);
$job = $this->queue->_enqueue_unique('_send_receipt', [$order_id]);  // not twice while it waits or runs
$job = $this->queue->_enqueue_in(60, '_send_receipt', [$order_id]);  // in a minute, by a worker
$job = $this->queue->_enqueue('_create', ['order_id' => $order_id], 'invoices');  // another module's
// ->handled (ran in this request, with ->result), ->is_waiting(), ->is_failed() with ->error_message

// The job's method: public, starts with _, so no URL reaches it
public function _send_receipt(int $order_id): void { ... }
```

To show a job's state next to a record, use `$this->queue->_pending('_send_receipt', [[1], [2]])`. It returns the waiting, running and failed unique jobs by `Job::key()`. A job that ran is gone.

## Without a worker

The dispatcher asks the queue whether a worker was seen recently (`queue_workers`). If not, a job that is due now runs in the request, so an app keeps working before its worker is deployed or while it is down. A failure there marks the job failed at once, and `_enqueue()` returns it rather than throwing. Jobs with a delay wait for a worker.

## Commands

```
php modules/queue/runtime.php work [queue ...] [--limit=N] [--time-limit=S] [--memory-limit=128M] [--sleep=1] [--stop-when-empty]
php modules/queue/runtime.php check
php modules/queue/runtime.php stats
php modules/queue/runtime.php failed:show [id]
php modules/queue/runtime.php failed:retry id ... | --all
php modules/queue/runtime.php failed:remove id ...
```

## Tests

The `.phpt` files are in `tests/`. The database queue's test needs `QUEUE_TEST_DSN` (plus `QUEUE_TEST_USER` and `QUEUE_TEST_PASSWORD`) pointing at a scratch MariaDB/MySQL database, where it drops and creates the queue tables. Without it, that test is skipped.
