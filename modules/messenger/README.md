# Messenger

"Run this later" for Trongate apps, after [Symfony Messenger](https://symfony.com/doc/current/messenger.html):

```php
Messenger::_later('Applications::_score', ['application_id' => $application_id], unique: true);
```

queues a call to the `Applications` controller's `_score(application_id: $application_id)`. A worker process makes the call, retries it when it throws, and keeps it with its error when it keeps failing.

A target is `Controller::_method`: a **public** method whose name starts with `_` on a controller in `modules/{controller}/`. Trongate's router never serves those from a URL, so a queued method can't be called from the browser.

Parameters are passed **by name**, so a call doesn't break when a method's parameters are reordered or one with a default is added. Values are `int`, `float`, `bool`, `string`, `null` or arrays of those, stored as one JSON object in `messenger_messages.parameters`.

## When a method's signature changes

The parameters are checked against the method with Reflection (every name exists, every required one is given, each value's type fits):

- **When queued:** `_later()` throws `InvalidArgumentException` saying what doesn't fit, so a mistake shows up in the request or a test, not in the worker.
- **Before a queued call runs:** a call queued under an older signature (say, a parameter renamed in a deploy) fails at once with that reason, without retries. It shows on `messenger/manage` and in `failed:show`; once the method fits again, Retry runs it.
- **When a worker starts:** `consume` first runs `check`, which logs every waiting or failed call that no longer fits. Run `php bin/messenger.php check` after a deploy to see them (exit code 1 when there are any).

To change a queued method safely, add the new parameter with a default, or keep the old name until the old calls have run.

The module is self-contained: nothing outside this folder but the Trongate engine, and only `Messenger.php` (the controller) needs that. The rest is plain PHP on PDO, so a framework-free worker can use it with its own runner.

## What's kept from Symfony Messenger

| Messenger | Here |
|---|---|
| Message + handler | A target and its named parameters: the controller method is the handler. |
| Bus | `Messenger::_later($target, $parameters, unique:, delay:)`, or `Message_bus::dispatch()`. |
| Transport | `Database_transport` (the tables in `sql/messenger.sql`), `In_memory_transport` for tests, or `'sync'` routing to run at once. |
| Envelope, stamps | `Envelope`: the call plus id, attempts, available_at, delivered_at, failed_at and the last error. |
| `messenger:consume` | `php bin/messenger.php consume` (`Worker`, `Messenger_console`). |
| Retry strategy | `Retry_strategy`, per transport: 3 retries, 1 s apart and doubling, at most 1 h. Throw `Unrecoverable_message_exception` to skip them. |
| Failure transport | A failed call stays in its row with `failed_at` and the error: `failed:show`, `failed:retry`, `failed:remove`, and the admin page `messenger/manage`. |

## Setting it up in an app

1. Put this folder at `modules/messenger` and add `sql/messenger.sql` to the app's schema.
2. Optionally, write `config/messenger.php` with transports, retries and routing (see `Messenger_runtime`). Without it there is one `async` transport with the default retries.
3. Add `bin/messenger.php`, which loads the app and runs the console. The recruiter's is a template: it loads `engine/ignition.php` with file sessions, then runs `Messenger_console` on `Messenger::_runtime()`.
4. Run a worker: `php bin/messenger.php consume --time-limit=3600`. Under Kubernetes or systemd, let it exit and be restarted; that keeps memory use and code fresh.

## Using it

```php
// In a controller: queue it, and say how it went
$envelope = Messenger::_later('Orders::_send_receipt', ['order_id' => $order_id], unique: true);
// ->handled (ran in this request, with ->result), ->is_waiting(), ->is_failed() with ->error_message

// The method it calls: public, starts with _, so no URL reaches it
public function _send_receipt(int $order_id): void { ... }
```

With `unique: true`, the same call (same target and parameters, in any order) isn't queued twice while it waits or runs, and a failed one is queued again. To show its state next to a record, use `Messenger::_pending('Orders::_send_receipt', [['order_id' => 1], ['order_id' => 2]])`. It returns the waiting, running and failed calls by `Envelope::key()`; a call that ran is gone.

A method that knows retrying can't help throws `Unrecoverable_message_exception`. Anything else it throws is retried, then failed.

## Without a worker

The bus asks the transport whether a worker was seen recently (`messenger_workers`). If not, a call due now runs in the request, so an app keeps working before its worker is deployed or while it is down. A failure there marks the call failed at once, and `_later()` returns it rather than throwing. Calls with a delay wait for a worker.

## Commands

```
php bin/messenger.php consume [transport ...] [--limit=N] [--time-limit=S] [--memory-limit=128M] [--sleep=1] [--stop-when-empty]
php bin/messenger.php check
php bin/messenger.php stats
php bin/messenger.php failed:show [id]
php bin/messenger.php failed:retry id ... | --all
php bin/messenger.php failed:remove id ...
```

## Tests

The `.phpt` files are in `tests/`. The database transport's test needs `MESSENGER_TEST_DSN` (plus `MESSENGER_TEST_USER` and `MESSENGER_TEST_PASSWORD`) pointing at a scratch MariaDB/MySQL database, where it drops and creates the messenger tables. Without it, that test is skipped.
