--TEST--
a CVR lookup reads cvrapi.dk's answer: the company, a closed one, and each error
--FILE--
<?php
require __DIR__ . '/../Cvr_lookup.php';
// Shaped like cvrapi.dk's answer (fields trimmed).
$found = [
    'vat' => 10150817, 'name' => 'Nordlys A/S', 'address' => 'Vestergade 1', 'zipcode' => '8000',
    'city' => 'Aarhus C', 'companydesc' => 'Aktieselskab', 'enddate' => null, 'startdate' => '01/01 - 1990',
];
echo json_encode(Cvr_lookup::read($found), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
echo json_encode(Cvr_lookup::read(['vat' => 1234567, 'name' => ' Gammel ApS ', 'enddate' => '31/12 - 2020'])), "\n";
foreach ([['error' => 'NOT_FOUND'], ['error' => 'QUOTA_EXCEEDED'], ['error' => 'SOMETHING_NEW'], ['vat' => 1], []] as $answer) {
    try {
        Cvr_lookup::read($answer);
    } catch (RuntimeException $e) {
        echo $e->getMessage(), "\n";
    }
}
echo (new Cvr_lookup())->request_url('10150817'), "\n";
echo (new Cvr_lookup('http://localhost/api', 'test', 'abc'))->request_url('10150817'), "\n";
try {
    (new Cvr_lookup())->fetch('1234 5678');
} catch (RuntimeException $e) {
    echo 'typed wrong: ', $e->getMessage(), "\n";
}
?>
--EXPECT--
{"cvr":"10150817","name":"Nordlys A/S","address":"Vestergade 1","postal_code":"8000","city":"Aarhus C","company_type":"Aktieselskab","ended":false}
{"cvr":"01234567","name":"Gammel ApS","address":"","postal_code":"","city":"","company_type":"","ended":true}
No company has that CVR number.
The CVR lookup is busy right now. Fill in the company's name yourself.
The CVR lookup isn't available right now. Fill in the company's name yourself.
The CVR lookup isn't available right now. Fill in the company's name yourself.
The CVR lookup isn't available right now. Fill in the company's name yourself.
https://cvrapi.dk/api?search=10150817&country=dk
http://localhost/api?search=10150817&country=dk&token=abc
typed wrong: That isn't a CVR number.
