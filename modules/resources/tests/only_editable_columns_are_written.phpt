--TEST--
an edit writes only the editable columns (and updated_at), and a delete only where the catalog allows it
--FILE--
<?php
require __DIR__ . '/setup.inc';
$candidates = Resource_catalog::find('candidates');

[$values, $errors] = $candidates->values_from([
    'name' => '  Ann Hansen ', 'email' => 'Ann@Example.DK', 'phone' => '', 'postal_code' => '4200',
    'active' => '1', 'password' => 'x', 'id' => '9',
]);
var_dump($values, $errors);
[$sql, $params] = $candidates->update_query([3], $values);
echo $sql, "\n";
var_dump(array_keys($params));

try {
    $candidates->update_query([3], ['password' => 'x']);
} catch (LogicException $e) {
    echo $e->getMessage(), "\n";
}
try {
    $candidates->delete_query([3]);
} catch (LogicException $e) {
    echo $e->getMessage(), "\n";
}
[$sql, $params] = Resource_catalog::find('job_posts')->delete_query([5]);
echo $sql, ' ', json_encode($params), "\n";

echo "what is wrong with a form:\n";
[, $errors] = $candidates->values_from(['name' => '', 'email' => 'not an email', 'postal_code' => str_repeat('1', 11)]);
var_dump($errors);
[, $errors] = Resource_catalog::find('companies')->values_from(['name' => 'Acme', 'cvr_number' => '12345678']);
var_dump($errors);
[$values] = Resource_catalog::find('companies')->values_from(['name' => 'Acme', 'cvr_number' => ' DK 1234 5674 ']);
var_dump($values['cvr_number'], $values['contact_email'], $values['active']);
[, $errors] = Resource_catalog::find('company_members')->values_from(['name' => 'Bo', 'email' => 'bo@acme.dk', 'role' => 'boss']);
var_dump($errors);
?>
--EXPECTF--
array(6) {
  ["name"]=>
  string(10) "Ann Hansen"
  ["email"]=>
  string(14) "ann@example.dk"
  ["phone"]=>
  NULL
  ["postal_code"]=>
  string(4) "4200"
  ["open_to_work"]=>
  int(0)
  ["active"]=>
  int(1)
}
array(0) {
}
UPDATE `candidates` SET `name` = :v_name, `email` = :v_email, `phone` = :v_phone, `postal_code` = :v_postal_code, `open_to_work` = :v_open_to_work, `active` = :v_active, `updated_at` = :v_updated_at WHERE `id` = :k_id
array(8) {
  [0]=>
  string(4) "k_id"
  [1]=>
  string(6) "v_name"
  [2]=>
  string(7) "v_email"
  [3]=>
  string(7) "v_phone"
  [4]=>
  string(13) "v_postal_code"
  [5]=>
  string(14) "v_open_to_work"
  [6]=>
  string(8) "v_active"
  [7]=>
  string(12) "v_updated_at"
}
candidates.password isn't editable.
Rows of candidates aren't deleted from the admin panel.
DELETE FROM `job_posts` WHERE `id` = :k_id {"k_id":5}
what is wrong with a form:
array(3) {
  ["name"]=>
  string(17) "Name is required."
  ["email"]=>
  string(29) "Email isn't an email address."
  ["postal_code"]=>
  string(47) "Postal code can't be longer than 10 characters."
}
array(1) {
  ["cvr_number"]=>
  string(43) "Cvr number isn't a valid Danish CVR number."
}
string(8) "12345674"
NULL
int(0)
array(1) {
  ["role"]=>
  string(38) "Role must be one of: owner, recruiter."
}
