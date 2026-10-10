<?php
/**
 * The list's rows (manage.php's tbody, and resources/more on its own), then
 * a "Show more" row when there are more. Without JS the link opens the
 * next rows as a page.
 *
 * @var Resource $resource
 * @var array[] $rows
 * @var string|null $next the next page's query string
 */
$columns = count($resource->listed) + 1;
foreach ($rows as $row):
    $key = $resource->key_of($row);
?>
    <tr id="row-<?= $key ?>">
        <?php foreach ($resource->listed as $column): ?>
            <td><?= $resource->fields[$column]->html($row[$column]) ?></td>
        <?php endforeach; ?>
        <td><a class="button alt" href="resources/show/<?= $resource->table ?>/<?= $key ?>">View</a></td>
    </tr>
<?php endforeach; ?>
<?php if ($next !== null): ?>
    <tr class="more-row">
        <td colspan="<?= $columns ?>">
            <a class="button alt more" href="resources/manage/<?= $resource->table ?>?<?= out($next) ?>" data-more="resources/more/<?= $resource->table ?>?<?= out($next) ?>">Show more</a>
        </td>
    </tr>
<?php endif; ?>
