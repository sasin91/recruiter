--TEST--
every table in db/schema.sql has an admin page, and a resource names every column of its table
--FILE--
<?php
require __DIR__ . '/setup.inc';
$schema = schema_columns();
$catalog = Resource_catalog::all();
$problems = [];

foreach ($schema as $table => $columns) {
    if (!isset($catalog[$table]) && !isset(Resource_catalog::ELSEWHERE[$table])) {
        $problems[] = "$table has no admin page";
    }
}
foreach ($catalog as $table => $r) {
    if (!isset($schema[$table])) {
        $problems[] = "$table isn't in db/schema.sql";
        continue;
    }
    $fields = array_keys($r->fields);
    foreach (array_diff($schema[$table], $fields) as $c) {
        $problems[] = "$table.$c is missing from the resource (add it, as Field::secret() if the admin mustn't see it)";
    }
    foreach (array_diff($fields, $schema[$table]) as $c) {
        $problems[] = "$table.$c isn't a column";
    }
    $visible = $r->visible();
    foreach (['key' => $r->key, 'listed' => $r->listed, 'searched' => $r->searched, 'editable' => $r->editable] as $what => $columns) {
        foreach (array_diff($columns, $visible) as $c) {
            $problems[] = "$table: $what column $c isn't a visible column";
        }
    }
    if ($r->title_column !== null && !in_array($r->title_column, $visible, true)) {
        $problems[] = "$table: title column $r->title_column isn't a visible column";
    }
    foreach ($r->editable as $c) {
        if (!in_array($r->fields[$c]->kind, ['int', 'decimal', 'text', 'long', 'bool', 'ref', 'code'], true) || in_array($c, $r->key, true)) {
            $problems[] = "$table.$c can't be editable";
        }
    }
    foreach ($r->fields as $c => $f) {
        if ($f->kind === 'ref' && !isset($catalog[$f->table])) {
            $problems[] = "$table.$c refers to $f->table, which has no resource";
        }
    }
}
echo $problems ? implode("\n", $problems) : 'all covered', "\n";
echo count($catalog), ' resources, ', count(Resource_catalog::ELSEWHERE), " elsewhere\n";
?>
--EXPECT--
all covered
30 resources, 3 elsewhere
