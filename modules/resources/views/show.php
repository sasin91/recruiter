<?php
/**
 * resources/show/{table}/{key}: every column but the secret ones, and
 * links to the lists that point at this row.
 *
 * @var Resource $resource
 * @var int[] $key
 * @var array $row
 * @var array<int, array{0: Resource, 1: string}> $related
 */
$id = implode('-', $key);
$title = $resource->title_column !== null ? (string) ($row[$resource->title_column] ?? '') : '';
?>
<p class="crumbs"><a href="resources">All tables</a> › <a href="resources/manage/<?= $resource->table ?>"><?= out($resource->name) ?></a></p>
<h1>#<?= $id ?><?= $title !== '' ? ' ' . out($title) : '' ?></h1>
<?= flashdata() ?>

<div class="card">
    <div class="card-heading"><?= out($resource->name) ?></div>
    <div class="card-body">
        <div class="text-right mb-3">
            <a class="button alt" href="resources/manage/<?= $resource->table ?>">Back</a>
            <?php if ($resource->editable): ?>
                <a class="button" href="resources/edit/<?= $resource->table ?>/<?= $id ?>">Edit</a>
            <?php endif; ?>
            <?php if ($resource->deletable): ?>
                <a class="button danger" href="resources/delete/<?= $resource->table ?>/<?= $id ?>">Delete</a>
            <?php endif; ?>
        </div>
        <div class="detail-grid">
            <?php foreach ($resource->visible() as $column): ?>
                <div class="detail-row">
                    <div class="detail-label"><?= out(Field::label($column)) ?></div>
                    <div class="detail-value"><?= $resource->fields[$column]->html($row[$column]) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php if ($related && count($key) === 1): ?>
    <h2>Related</h2>
    <ul class="resource-related">
        <?php foreach ($related as [$other, $column]): ?>
            <li><a href="resources/manage/<?= $other->table ?>?<?= $column ?>=<?= (int) $key[0] ?>"><?= out($other->name) ?></a>
                <small>by <?= out(strtolower(Field::label($column))) ?></small></li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>
