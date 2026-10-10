<?php
/**
 * queue/manage: per queue its counts and jobs, and the workers.
 *
 * @var array $queues name => ['counts' => [...], 'failed' => Job[], 'waiting' => Job[]]
 * @var array $workers queue_workers rows, newest first
 * @var array $live the workers seen in the last minute
 */
$when = fn(?int $t) => $t === null ? '' : date('j M H:i:s', $t);
$button = fn(string $action, int $id, string $label) => form_open("queue/submit_$action/$id", ['style' => 'display:inline'])
    . form_submit('submit', $label, ['class' => 'button alt'])
    . form_close();
?>
<h1>Queued jobs</h1>
<?= flashdata() ?>

<?php if (!$live): ?>
    <p><strong>No worker is running.</strong> Jobs run in the request instead, and jobs with a delay wait until a worker runs <code>php modules/queue/runtime.php work</code>.</p>
<?php endif; ?>

<?php foreach ($queues as $name => $t): ?>
    <h2><?= out($name) ?></h2>
    <p><?= $t['counts']['waiting'] ?> waiting, <?= $t['counts']['running'] ?> running, <?= $t['counts']['failed'] ?> failed.</p>

    <?php if ($t['failed']): ?>
        <h3>Failed</h3>
        <table class="records-table">
            <thead><tr><th>#</th><th>Job</th><th>Failed</th><th>Tries</th><th>Error</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($t['failed'] as $e): ?>
                <tr>
                    <td><?= (int) $e->id ?></td>
                    <td><code><?= out($e->label()) ?></code></td>
                    <td><?= $when($e->failed_at) ?></td>
                    <td><?= (int) $e->attempts ?></td>
                    <td><small><?= out((string) $e->error_class) ?></small><br><?= out((string) $e->error_message) ?></td>
                    <td><?= $button('retry', (int) $e->id, 'Retry') ?> <?= $button('remove', (int) $e->id, 'Remove') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if ($t['waiting']): ?>
        <h3>Waiting and running</h3>
        <table class="records-table">
            <thead><tr><th>#</th><th>Job</th><th>State</th><th>Tries</th><th>Last error</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($t['waiting'] as $e): ?>
                <tr>
                    <td><?= (int) $e->id ?></td>
                    <td><code><?= out($e->label()) ?></code></td>
                    <td><?= $e->is_running() ? 'Running since ' . $when($e->reserved_at) : 'Due ' . $when($e->available_at) ?></td>
                    <td><?= (int) $e->attempts ?></td>
                    <td><?= out((string) $e->error_message) ?></td>
                    <td><?= $e->is_running() ? '' : $button('remove', (int) $e->id, 'Remove') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
<?php endforeach; ?>

<h2>Workers</h2>
<?php if (!$workers): ?>
    <p>No worker has run in the last 7 days.</p>
<?php else: ?>
    <table class="records-table">
        <thead><tr><th>Worker</th><th>Host</th><th>Queues</th><th>Handled</th><th>Failed</th><th>Started</th><th>Last seen</th><th>Stopped</th></tr></thead>
        <tbody>
        <?php foreach ($workers as $w): ?>
            <tr>
                <td><code><?= out($w['id']) ?></code></td>
                <td><?= out($w['hostname']) ?> (<?= (int) $w['process_id'] ?>)</td>
                <td><?= out($w['queues']) ?></td>
                <td><?= (int) $w['handled'] ?></td>
                <td><?= (int) $w['failed'] ?></td>
                <td><?= $when((int) $w['started_at']) ?></td>
                <td><?= $when((int) $w['last_seen_at']) ?></td>
                <td><?= $w['stopped_at'] !== null ? $when((int) $w['stopped_at']) : ((int) $w['last_seen_at'] >= time() - 60 ? 'running' : 'stopped answering') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
