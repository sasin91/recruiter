--TEST--
a job is Controller::_method with named parameters that survive JSON; anything else is refused when queued
--FILE--
<?php
require __DIR__ . '/setup.inc';
$e = Job::create('Notes::_save', ['times' => 2, 'text' => "Hej\nmed dig", 'weights' => [0.5, 1.0], 'ok' => true, 'none' => null], unique: true);
echo $e->label(), "\n", $e->parameters_json(), "\n";
var_dump(Job::parameters_from_json($e->parameters_json()) === $e->parameters, Job::parameters_from_json('{}'));
echo Job::key('Notes::_save', ['text' => 'a', 'times' => 2]) === Job::key('Notes::_save', ['times' => 2, 'text' => 'a']) ? "order doesn't matter\n" : "order matters\n";
echo strlen(Job::key('Notes::_save', ['text' => str_repeat('x', 300)])), "\n";
foreach ([['notes/_save', []], ['Notes::save', []], ['Notes::_save', [42]], ['Notes::_save', ['text' => new stdClass()]], ['Notes::_save', ['text' => [NAN]]]] as [$method, $parameters]) {
    try {
        Job::create($method, $parameters);
        echo "accepted $method\n";
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
Notes::_save(none: null, ok: true, text: "Hej\nmed dig", times: 2, weights: [0.5,1])
{"times":2,"text":"Hej\nmed dig","weights":[0.5,1.0],"ok":true,"none":null}
bool(true)
array(0) {
}
order doesn't matter
53
notes/_save isn't a job method: use Controller::_method, a public method whose name starts with _.
Notes::save isn't a job method: use Controller::_method, a public method whose name starts with _.
Parameters of Notes::_save are passed by name, e.g. ['application_id' => 42].
Parameter text of Notes::_save is stdClass; a job takes int, float, bool, string, null or arrays of those.
Parameter text of Notes::_save isn't a finite number.
