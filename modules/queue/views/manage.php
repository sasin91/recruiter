<?php
/**
 * queue/manage: per queue its counts and jobs, and the workers.
 *
 * @var array $queues name => ['counts' => [...], 'failed' => rows, 'waiting' => rows] (Queue_job_row::from)
 * @var array $workers newest first (Queue_worker_row::from)
 * @var array $live the workers seen in the last minute
 */
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
                    <td><?= $e['id'] ?></td>
                    <td><code><?= out($e['label']) ?></code></td>
                    <td><?= $e['failed'] ?></td>
                    <td><?= $e['attempts'] ?></td>
                    <td><small><?= out($e['error_class']) ?></small><br><?= out($e['error']) ?></td>
                    <td><?php require __DIR__ . '/job_buttons.php'; ?></td>
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
                    <td><?= $e['id'] ?></td>
                    <td><code><?= out($e['label']) ?></code></td>
                    <td><?= $e['state'] ?></td>
                    <td><?= $e['attempts'] ?></td>
                    <td><?= out($e['error']) ?></td>
                    <td><?php require __DIR__ . '/job_buttons.php'; ?></td>
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
                <td><?= out($w['host']) ?></td>
                <td><?= out($w['queues']) ?></td>
                <td><?= $w['handled'] ?></td>
                <td><?= $w['failed'] ?></td>
                <td><?= $w['started'] ?></td>
                <td><?= $w['last_seen'] ?></td>
                <td><?= $w['stopped'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
