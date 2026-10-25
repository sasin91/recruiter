--TEST--
OpenAI is asked not to store what it is sent (store: false), on both the Chat Completions and the Responses API
--FILE--
<?php
require __DIR__ . '/setup.inc';
$schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['ok'], 'properties' => ['ok' => ['type' => 'boolean']]];

[$server, $url, $request] = canned_server(['choices' => [['finish_reason' => 'stop', 'message' => ['content' => '{"ok":true}']]]]);
$openai = new Openai();
$openai->configure(['api_key' => 'sk-test', 'base_url' => $url, 'model' => 'gpt-5.4-mini']);
$openai->structured('Read the CV.', 'A CV', $schema);
var_dump(json_decode(file_get_contents($request), true)['store']);
proc_terminate($server);

[$server, $url, $request] = canned_server(['status' => 'completed', 'output' => [['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"ok":true}']]]]]);
$openai->configure(['api_key' => 'sk-test', 'base_url' => $url, 'model' => 'gpt-5.4-mini']);
$openai->web_search('Find jobs.', 'PHP in Odense', $schema);
var_dump(json_decode(file_get_contents($request), true)['store']);
proc_terminate($server);
?>
--EXPECT--
bool(false)
bool(false)
