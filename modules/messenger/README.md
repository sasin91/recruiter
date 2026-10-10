# Messenger

A queue and worker for Trongate apps, modelled on [Symfony Messenger](https://symfony.com/doc/current/messenger.html). Send a message from a request; a worker process handles it later, retries it when it fails, and keeps it with its error when it keeps failing.

The module is self-contained: nothing outside this folder but the Trongate engine (and only `Messenger.php`, the controller, needs that). The rest is plain PHP on PDO, so a framework-free worker can use it too.

## Concepts

| Messenger | Here |
|---|---|
| Message | A class whose constructor takes its public properties, each `int`, `float`, `bool`, `string` or `null`. Implement `Deduplicated_message` to queue it once per key. |
| Handler | Any callable taking the message, usually a class with `__invoke()`. |
| Bus | `Messenger::dispatch($message, $delay)` (or `Message_bus` directly). |
| Transport | `Database_transport` (the tables in `sql/messenger.sql`), `In_memory_transport` for tests, or `sync` routing to handle at once. |
| Envelope, stamps | `Envelope`: the message plus id, attempts, available_at, delivered_at, failed_at and the last error. |
| `messenger:consume` | `php bin/messenger.php consume` (`Worker`, `Messenger_console`). |
| Retry strategy | `Retry_strategy`, per transport: 3 retries, 1 s, ×2, at most 1 h. `Unrecoverable_message_exception` skips them. |
| Failure transport | A failed message stays in its row with `failed_at` and the error: `failed:show`, `failed:retry`, `failed:remove`, and the admin page `messenger/manage`. |

## Setting it up in an app

1. Copy this folder to `modules/messenger` and add `sql/messenger.sql` to the app's schema.
2. Write `config/messenger.php` (see `Messenger_runtime` for every option):

   ```php
   <?php
   require_once APPPATH . 'modules/orders/Send_receipt.php';

   return [
       'transports' => ['async' => ['max_retries' => 3, 'delay' => 1, 'multiplier' => 2]],
       'routing' => ['Send_receipt' => 'async'],
       'handlers' => [
           'Send_receipt' => function () {
               require_once APPPATH . 'modules/orders/Send_receipt_handler.php';
               return new Send_receipt_handler();
           },
       ],
       // No worker seen for 60 s: handle due messages in the request instead (0 = never)
       'in_request_without_worker' => 60,
   ];
   ```

3. Add `bin/messenger.php`, which loads the app and runs the console (the recruiter's is a template: it loads `engine/ignition.php` with file sessions, then `Messenger_console`).
4. Run a worker: `php bin/messenger.php consume --time-limit=3600`. Under Kubernetes or systemd let it exit and be restarted; that keeps memory and code fresh.

## Using it

```php
final class Send_receipt implements Deduplicated_message {
    public function __construct(public readonly int $order_id) {}
    public function dedupe_key(): string { return "send_receipt:{$this->order_id}"; }
}

$envelope = Messenger::dispatch(new Send_receipt($order_id));
// $envelope->handled (ran in this request), ->is_waiting(), ->is_failed(), ->error_message
```

To show the state next to a record, look its message up by key: `Messenger::by_dedupe_keys(["send_receipt:$id"])`. A handled message is gone; a waiting, running or failed one is there, with its last error.

A handler that knows retrying can't help throws `Unrecoverable_message_exception`. Anything else is retried, then failed.

## Without a worker

`Message_bus` asks the transport whether a worker was seen recently (`messenger_workers`). If not, a message due now is handled in the request, so an app keeps working before its worker is deployed or while it is down. A failure there marks the message failed at once and `dispatch()` returns it; it doesn't throw. Messages with a delay wait for a worker.

## Commands

```
php bin/messenger.php consume [transport ...] [--limit=N] [--time-limit=S] [--memory-limit=128M] [--sleep=1] [--stop-when-empty]
php bin/messenger.php stats
php bin/messenger.php failed:show [id]
php bin/messenger.php failed:retry id ... | --all
php bin/messenger.php failed:remove id ...
```

## Tests

`.phpt` files in `tests/`. The database transport's test needs `MESSENGER_TEST_DSN` (and `MESSENGER_TEST_USER`, `MESSENGER_TEST_PASSWORD`) pointing at a scratch MariaDB/MySQL database, where it drops and creates the messenger tables; without it the test is skipped.
