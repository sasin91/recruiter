--TEST--
every table in db/schema.sql has an admin page, and none shows a password, token or encrypted key
--FILE--
<?php
$root = __DIR__ . '/../../..';
preg_match_all('/CREATE TABLE IF NOT EXISTS `(\w+)`/', file_get_contents("$root/db/schema.sql"), $m);
$tables = $m[1];

// Tables whose admin page isn't a generated module of their own.
$elsewhere = ['queue_jobs' => 'queue/manage', 'queue_workers' => 'queue/manage'];

$modules = [];
foreach (glob("$root/modules/{*,*/*}/*_model.php", GLOB_BRACE) as $model) {
    if (preg_match("/private string \\\$table_name = '(\w+)';/", file_get_contents($model), $t)) {
        $modules[$t[1]] = dirname($model);
    }
}
$admin = file_get_contents("$root/modules/templates/views/admin.php");
foreach ($tables as $table) {
    if (isset($elsewhere[$table])) {
        continue;
    }
    if (!isset($modules[$table])) {
        echo "$table has no admin module\n";
        continue;
    }
    $dir = $modules[$table];
    if (!is_file("$dir/views/manage.php")) {
        echo "$table has no manage page\n";
    }
    $url = str_replace('/', '-', substr($dir, strlen("$root/modules/"))) . '/manage';
    if (!str_contains($admin, "'$url'")) {
        echo "$table ($url) isn't in the admin menu\n";
    }
    // Columns no page shows: password hashes, tokens, encrypted keys (and a user's session code).
    $secrets = $table === 'trongate_users' ? 'code' : 'password|invite_token|token|api_key_encrypted';
    foreach (glob("$dir/views/*.php") as $view) {
        if (preg_match('/\$(?:row->)?(' . $secrets . ')\b/', file_get_contents($view), $s)) {
            echo "$table: " . basename($view) . " shows $s[1]\n";
        }
    }
}
echo count($tables), " tables checked\n";
?>
--EXPECT--
33 tables checked
