--TEST--
a job is a module, a _method and named parameters that survive JSON; anything else is refused when queued
--FILE--
<?php
require __DIR__ . '/setup.inc';
$e = Job::create('notes', '_save', ['times' => 2, 'text' => "Hej\nmed dig", 'weights' => [0.5, 1.0], 'ok' => true, 'none' => null], unique: true);
echo $e->label(), "\n", $e->parameters_json(), "\n";
var_dump(Job::parameters_from_json($e->parameters_json()) === $e->parameters, Job::parameters_from_json('{}'));
echo Job::key('notes', '_save', ['text' => 'a', 'times' => 2]) === Job::key('notes', '_save', ['times' => 2, 'text' => 'a']) ? "order doesn't matter\n" : "order matters\n";
echo strlen(Job::key('notes', '_save', ['text' => str_repeat('x', 300)])), "\n";
foreach ([['Notes', '_save', []], ['notes/x', '_save', []], ['notes', 'save', []], ['notes', '_save', [42]], ['notes', '_save', ['text' => new stdClass()]], ['notes', '_save', ['text' => [NAN]]], ['llm-openai', '_ask', []]] as [$module, $method, $parameters]) {
    try {
        Job::create($module, $method, $parameters);
        echo "accepted $module/$method\n";
    } catch (InvalidArgumentException $e) {
        echo $e->getMessage(), "\n";
    }
}
?>
--EXPECT--
notes/_save(none: null, ok: true, text: "Hej\nmed dig", times: 2, weights: [0.5,1])
{"times":2,"text":"Hej\nmed dig","weights":[0.5,1.0],"ok":true,"none":null}
bool(true)
array(0) {
}
order doesn't matter
52
Notes isn't a module: use its folder name in lower case, e.g. 'applications'.
notes/x isn't a module: use its folder name in lower case, e.g. 'applications'.
notes/save can't be a job: its method must be public and start with _, so no URL reaches it.
Parameters of notes/_save are passed by name, e.g. ['application_id' => 42].
Parameter text of notes/_save is stdClass; a job takes int, float, bool, string, null or arrays of those.
Parameter text of notes/_save isn't a finite number.
accepted llm-openai/_ask