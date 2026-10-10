--TEST--
a list pages newest first by key, with filters, a search and a cursor, from the catalog's names only
--FILE--
<?php
require __DIR__ . '/setup.inc';
$members = Resource_catalog::find('company_members');

[$sql, $params] = $members->list_query([], '', null, 50);
echo $sql, "\n";

$filters = $members->filters_from(['company_id' => '7', 'role' => 'owner', 'password' => 'x', 'name' => 'Ann', 'active' => 'yes']);
var_dump($filters);
[$sql, $params] = $members->list_query($filters, '50%_off', [120], 50);
echo $sql, "\n";
var_dump($params);

echo "a number also finds the id:\n";
[$sql] = $members->list_query([], '42', null, 10);
echo $sql, "\n";

echo "a two-column key:\n";
$views = Resource_catalog::find('job_post_member_views');
var_dump($views->parse_key('12-5'), $views->parse_key('12'), $views->parse_key('12-x'));
echo $views->key_of(['job_post_id' => '12', 'company_member_id' => '5', 'last_viewed_at' => 1]), "\n";
[$sql, $params] = $views->list_query([], '', [12, 5], 50);
echo $sql, "\n";
[$sql] = $views->list_query([], 'anything', null, 50);
echo $sql, "\n";
?>
--EXPECT--
SELECT `id`, `name`, `email`, `company_id`, `role`, `active`, `last_login` FROM `company_members` ORDER BY `id` DESC LIMIT 51
array(2) {
  ["company_id"]=>
  int(7)
  ["role"]=>
  string(5) "owner"
}
SELECT `id`, `name`, `email`, `company_id`, `role`, `active`, `last_login` FROM `company_members` WHERE `company_id` = :f_company_id AND `role` = :f_role AND (`name` LIKE :q0 OR `email` LIKE :q1) AND (`id`) < (:after0) ORDER BY `id` DESC LIMIT 51
array(5) {
  ["f_company_id"]=>
  int(7)
  ["f_role"]=>
  string(5) "owner"
  ["q0"]=>
  string(11) "%50\%\_off%"
  ["q1"]=>
  string(11) "%50\%\_off%"
  ["after0"]=>
  int(120)
}
a number also finds the id:
SELECT `id`, `name`, `email`, `company_id`, `role`, `active`, `last_login` FROM `company_members` WHERE (`name` LIKE :q0 OR `email` LIKE :q1 OR `id` = :q_key) ORDER BY `id` DESC LIMIT 11
a two-column key:
array(2) {
  [0]=>
  int(12)
  [1]=>
  int(5)
}
NULL
NULL
12-5
SELECT `job_post_id`, `company_member_id`, `last_viewed_at` FROM `job_post_member_views` WHERE (`job_post_id`, `company_member_id`) < (:after0, :after1) ORDER BY `job_post_id` DESC, `company_member_id` DESC LIMIT 51
SELECT `job_post_id`, `company_member_id`, `last_viewed_at` FROM `job_post_member_views` WHERE 0 = 1 ORDER BY `job_post_id` DESC, `company_member_id` DESC LIMIT 51
