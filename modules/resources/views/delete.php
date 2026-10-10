<?php
/**
 * resources/delete/{table}/{key}: asks before deleting, and says why it
 * didn't when the database refused ($error).
 *
 * @var Resource $resource
 * @var int[] $key
 * @var array $row
 * @var string|null $error
 */
$id = implode('-', $key);
$title = $resource->title_column !== null ? (string) ($row[$resource->title_column] ?? '') : '';
?>
<p class="crumbs"><a href="resources">All tables</a> › <a href="resources/manage/<?= $resource->table ?>"><?= out($resource->name) ?></a> › <a href="resources/show/<?= $resource->table ?>/<?= $id ?>">#<?= $id ?></a></p>
<h1>Delete #<?= $id ?><?= $title !== '' ? ' ' . out($title) : '' ?>?</h1>

<?php if ($error !== null): ?>
    <div class="validation-errors" role="alert"><div>&#9679; <?= out($error) ?></div></div>
<?php endif; ?>

<div class="card">
    <div class="card-heading">This can't be undone</div>
    <div class="card-body">
        <p>This deletes the row from <?= out($resource->name) ?> for good.<?= $resource->delete_note !== '' ? ' ' . out($resource->delete_note) : '' ?></p>
        <?= form_open("resources/submit_delete/$resource->table/$id") ?>
        <div class="text-center">
            <a class="button alt" href="resources/show/<?= $resource->table ?>/<?= $id ?>">Cancel</a>
            <?= form_submit('submit', 'Delete', ['class' => 'danger']) ?>
        </div>
        <?= form_close() ?>
    </div>
</div>
