<?php
require_once __DIR__ . '/../Resource_catalog.php';
require_once __DIR__ . '/../Resource_refused.php';

/** db/schema.sql's tables: table => its columns in order (ALTER ... ADD COLUMN included). */
function schema_columns(): array {
    $sql = file_get_contents(__DIR__ . '/../../../db/schema.sql');
    $tables = [];
    preg_match_all('/CREATE TABLE IF NOT EXISTS `(\w+)` \((.*?)\n\) ENGINE/s', $sql, $creates, PREG_SET_ORDER);
    foreach ($creates as [, $table, $body]) {
        preg_match_all('/^  `(\w+)` /m', $body, $m);
        $tables[$table] = $m[1];
    }
    preg_match_all('/ALTER TABLE `(\w+)`(.*?);/s', $sql, $alters, PREG_SET_ORDER);
    foreach ($alters as [, $table, $body]) {
        preg_match_all('/ADD COLUMN IF NOT EXISTS `(\w+)`/', $body, $m);
        foreach ($m[1] as $column) {
            if (!in_array($column, $tables[$table], true)) {
                $tables[$table][] = $column;
            }
        }
    }
    return $tables;
}
