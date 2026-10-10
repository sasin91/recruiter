<?php
require_once __DIR__ . '/Resource_catalog.php';

/** A change the database refused, said so the admin knows what to do. */
class Resource_refused extends RuntimeException {

    /** The message for a duplicate key or a foreign key error, else null. */
    public static function explain(Resource $resource, PDOException $e): ?string {
        $code = (int) ($e->errorInfo[1] ?? 0);
        $message = (string) ($e->errorInfo[2] ?? $e->getMessage());
        if ($code === 1062) {
            preg_match("/for key '(?:[^'.]+\\.)?([^']+)'/", $message, $m);
            $column = $m[1] ?? '';
            $what = isset($resource->fields[$column]) ? 'that ' . strtolower(Field::label($column)) : 'one of these values';
            return "Another row of $resource->name already has $what.";
        }
        if ($code === 1451) {
            preg_match('/`([a-z_]+)`, CONSTRAINT/', $message, $m);
            $by = isset($m[1]) ? (Resource_catalog::find($m[1])?->name ?? $m[1]) : 'other rows';
            return "Other rows still point at it ($by). Delete those first.";
        }
        if ($code === 1452) {
            return 'One of the ids refers to a row that doesn\'t exist.';
        }
        return null;
    }
}
