<?php
/**
 * resources: every table the admin panel shows, grouped.
 *
 * @var array<string, Resource[]> $groups
 * @var array<string, string> $elsewhere table => the admin page that has it
 */
?>
<h1>All tables</h1>
<p>Accounts can be edited, not deleted: switch them off instead. What the app writes is read-only; posts, applications, CV matches, AI keys and sign-in rows can be deleted.</p>
<?php foreach ($groups as $group => $resources): ?>
    <h2><?= out($group) ?></h2>
    <table class="records-table resources-index">
        <tbody>
        <?php foreach ($resources as $r): ?>
            <tr>
                <td><a href="resources/manage/<?= $r->table ?>"><?= out($r->name) ?></a><br><code><?= $r->table ?></code></td>
                <td><?= out($r->note) ?></td>
                <td><?= $r->read_only() ? 'Read-only' : implode(', ', array_filter([$r->editable ? 'Edit' : '', $r->deletable ? 'Delete' : ''])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endforeach; ?>

<h2>Elsewhere</h2>
<ul>
    <?php foreach ($elsewhere as $table => $url): ?>
        <li><code><?= $table ?></code>: <a href="<?= $url ?>"><?= $url ?></a></li>
    <?php endforeach; ?>
</ul>
