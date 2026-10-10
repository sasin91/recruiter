<?php
/**
 * messenger/manage: per transport its counts and calls, and the workers.
 *
 * @var array $transports name => ['counts' => [...], 'failed' => Envelope[], 'waiting' => Envelope[]]
 * @var array $workers messenger_workers rows, newest first
 * @var array $live the workers seen in the last minute
 */
$when = fn(?int $t) => $t === null ? '' : date('j M H:i:s', $t);
$button = fn(string $action, int $id, string $label) => form_open("messenger/submit_$action/$id", ['style' => 'display:inline'])
    . form_submit('submit', $label, ['class' => 'button alt'])
    . form_close();
?>
<h1>Queued calls</h1>
<?= flashdata() ?>

<?php if (!$live): ?>
    <p><strong>No worker is running.</strong> Calls run in the request instead, and calls with a delay wait until a worker runs <code>php bin/messenger.php consume</code>.</p>
<?php endif; ?>

<?php foreach ($transports as $name => $t): ?>
    <h2><?= out($name) ?></h2>
    <p><?= $t['counts']['waiting'] ?> waiting, <?= $t['counts']['running'] ?> running, <?= $t['counts']['failed'] ?> failed.</p>

    <?php if ($t['failed']): ?>
        <h3>Failed</h3>
        <table class="records-table">
            <thead><tr><th>#</th><th>Call</th><th>Failed</th><th>Tries</th><th>Error</th><th></th></tr></thead>
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
            <thead><tr><th>#</th><th>Call</th><th>State</th><th>Tries</th><th>Last error</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($t['waiting'] as $e): ?>
                <tr>
                    <td><?= (int) $e->id ?></td>
                    <td><code><?= out($e->label()) ?></code></td>
                    <td><?= $e->is_running() ? 'Running since ' . $when($e->delivered_at) : 'Due ' . $when($e->available_at) ?></td>
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
        <thead><tr><th>Worker</th><th>Host</th><th>Transports</th><th>Handled</th><th>Failed</th><th>Started</th><th>Last seen</th><th>Stopped</th></tr></thead>
        <tbody>
        <?php foreach ($workers as $w): ?>
            <tr>
                <td><code><?= out($w['id']) ?></code></td>
                <td><?= out($w['hostname']) ?> (<?= (int) $w['process_id'] ?>)</td>
                <td><?= out($w['transports']) ?></td>
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
