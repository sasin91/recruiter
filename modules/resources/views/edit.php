<?php
/**
 * resources/edit/{table}/{key}: the editable columns, and what was wrong
 * with the last try ($errors: column => message, '' for the whole form).
 *
 * @var Resource $resource
 * @var int[] $key
 * @var array $row
 * @var array $values what the form shows: the row, or what was posted
 * @var array $errors
 */
$id = implode('-', $key);
?>
<p class="crumbs"><a href="resources">All tables</a> › <a href="resources/manage/<?= $resource->table ?>"><?= out($resource->name) ?></a> › <a href="resources/show/<?= $resource->table ?>/<?= $id ?>">#<?= $id ?></a></p>
<h1>Edit #<?= $id ?></h1>

<?php if ($errors): ?>
    <div class="validation-errors" role="alert">
        <?php foreach ($errors as $message): ?>
            <div>&#9679; <?= out($message) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-heading"><?= out($resource->name) ?></div>
    <div class="card-body">
        <?= form_open("resources/submit_edit/$resource->table/$id") ?>
        <?php foreach ($resource->editable as $column):
            $field = $resource->fields[$column];
            $value = $values[$column] ?? null;
            $value = $value === null ? '' : (string) $value;
            $attributes = ['id' => $column] + (isset($errors[$column]) ? ['class' => 'form-field-validation-error', 'aria-invalid' => 'true'] : []);
        ?>
            <?php if ($field->kind === 'bool'): ?>
                <label><?= form_checkbox($column, '1', $value === '1', $attributes) ?> <?= out(Field::label($column)) ?></label>
            <?php else: ?>
                <?= form_label(out(Field::label($column)) . ($field->nullable ? ' <small>(optional)</small>' : ''), ['for' => $column]) ?>
                <?php if ($field->kind === 'code'): ?>
                    <?= form_dropdown($column, array_combine($field->options, $field->options), $value, $attributes) ?>
                <?php elseif ($field->kind === 'long'): ?>
                    <?= form_textarea($column, $value, $attributes) ?>
                <?php elseif (in_array($field->kind, ['int', 'ref'], true)): ?>
                    <?= form_number($column, $value, $attributes) ?>
                <?php else: ?>
                    <?= form_input($column, $value, $attributes + ($field->max ? ['maxlength' => $field->max] : [])) ?>
                <?php endif; ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <div class="text-center">
            <a class="button alt" href="resources/show/<?= $resource->table ?>/<?= $id ?>">Cancel</a>
            <?= form_submit('submit', 'Save') ?>
        </div>
        <?= form_close() ?>
    </div>
</div>
