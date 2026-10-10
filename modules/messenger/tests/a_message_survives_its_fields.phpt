--TEST--
a message goes to name/type/value rows and comes back the same; arrays and objects are refused
--FILE--
<?php
require __DIR__ . '/setup.inc';
$message = new Send_greeting(42, "Hej\nmed dig", 0.5, true);
$fields = Message_fields::from_message($message);
print_r($fields);
var_dump(Message_fields::to_message('Send_greeting', $fields) == $message);
var_dump(Message_fields::to_message('Send_greeting', Message_fields::from_message(new Send_greeting(7)))->weight);

final class Bad_message {
    public function __construct(public readonly array $ids) {
    }
}
try {
    Message_fields::from_message(new Bad_message([1]));
} catch (InvalidArgumentException $e) {
    echo $e->getMessage(), "\n";
}
try {
    Message_fields::to_message('Gone_message', []);
} catch (Unrecoverable_message_exception $e) {
    echo $e->getMessage(), "\n";
}
?>
--EXPECT--
Array
(
    [person_id] => Array
        (
            [0] => int
            [1] => 42
        )

    [text] => Array
        (
            [0] => string
            [1] => Hej
med dig
        )

    [weight] => Array
        (
            [0] => float
            [1] => 0.5
        )

    [loud] => Array
        (
            [0] => bool
            [1] => 1
        )

)
bool(true)
NULL
Bad_message::$ids is array; a message holds only int, float, bool, string or null.
Message class Gone_message isn't loaded; is it required in config/messenger.php?
