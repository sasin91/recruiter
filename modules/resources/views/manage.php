<?php
/**
 * resources/manage/{table}: the table's rows, newest first, PER_PAGE at a
 * time; more.js loads the next ones as the end scrolls into view.
 *
 * @var Resource $resource
 * @var array $filters column => value
 * @var array $query the list's query string (filters and search) as an array
 * @var string $search
 */
?>
<p class="crumbs"><a href="resources">All tables</a> › <?= out($resource->group) ?></p>
<h1><?= out($resource->name) ?></h1>
<p><?= out($resource->note) ?></p>
<?= flashdata() ?>

<form class="resource-search" method="get" action="resources/manage/<?= $resource->table ?>">
    <?php foreach ($filters as $column => $value): ?>
        <input type="hidden" name="<?= $column ?>" value="<?= out((string) $value) ?>">
    <?php endforeach; ?>
    <?php if ($resource->searched || count($resource->key) === 1): ?>
        <input type="search" name="q" value="<?= out($search) ?>" aria-label="Search"
               placeholder="<?= out(implode(', ', array_map(fn($c) => strtolower(Field::label($c)), $resource->searched)) ?: 'id') ?>">
        <button type="submit">Search</button>
    <?php endif; ?>
    <?php if ($query): ?>
        <a class="button alt" href="resources/manage/<?= $resource->table ?>">Show all</a>
    <?php endif; ?>
</form>

<?php if ($filters): ?>
    <p class="resource-filters">Only rows where
        <?= implode(' and ', array_map(fn($c, $v) => '<strong>' . out(strtolower(Field::label($c))) . '</strong> is ' . $resource->fields[$c]->html($v), array_keys($filters), $filters)) ?>.
    </p>
<?php endif; ?>

<?php if (!$rows): ?>
    <p><?= $query ? 'Nothing matches.' : 'There are no rows yet.' ?></p>
<?php else: ?>
    <div class="table-container">
        <table class="records-table resource-list">
            <thead>
                <tr>
                    <?php foreach ($resource->listed as $column): ?>
                        <th><?= out(Field::label($column)) ?></th>
                    <?php endforeach; ?>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php require __DIR__ . '/rows.php'; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
