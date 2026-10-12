--TEST--
the questions an application instruction lists are quoted with it, and nothing after them
--FILE--
<?php
require_once __DIR__ . '/../Apply_instructions.php';
$found = Apply_instructions::read("Om jobbet\n\nSend en mail til job@firma.dk og besvar følgende spørgsmål:\n\n- Hvorfor os?\n- Hvad er dit yndlingsværktøj?\n\nVi holder samtaler løbende.");
print_r($found['lines']);
echo Apply_instructions::for_prompt($found), "\n";
var_dump(Apply_instructions::for_prompt(null));
?>
--EXPECT--
Array
(
    [0] => Send en mail til job@firma.dk og besvar følgende spørgsmål:
    [1] => - Hvorfor os?
    [2] => - Hvad er dit yndlingsværktøj?
)
How the post asks to be applied to (it asks for: answers to questions):
> Send en mail til job@firma.dk og besvar følgende spørgsmål:
> - Hvorfor os?
> - Hvad er dit yndlingsværktøj?
string(0) ""
