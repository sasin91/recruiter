--TEST--
OpenAI's web_search asks the Responses API with the web_search tool and reads its answer and token use
--FILE--
<?php
require __DIR__ . '/setup.inc';
[$server, $url, $request] = canned_server([
    'status' => 'completed',
    'output' => [
        ['type' => 'web_search_call', 'status' => 'completed'],
        ['type' => 'message', 'content' => [['type' => 'output_text', 'text' => '{"posts":[{"url":"https://jobnet.dk/job/1"}]}']]],
    ],
    'usage' => ['input_tokens' => 1200, 'output_tokens' => 300],
]);
$openai = new Openai();
$openai->configure(['api_key' => 'sk-test', 'base_url' => $url, 'model' => 'gpt-5.4-mini']);
$schema = ['type' => 'object', 'additionalProperties' => false, 'required' => ['posts'], 'properties' => ['posts' => ['type' => 'array', 'items' => ['type' => 'object']]]];
var_dump($openai->web_search('Find jobs.', 'PHP in Odense', $schema, ['jobnet.dk'], 'DK'));
$sent = json_decode(file_get_contents($request), true);
var_dump($sent['instructions'], $sent['tools'], $sent['text']['format']['type']);
proc_terminate($server);
?>
--EXPECT--
array(3) {
  ["answer"]=>
  array(1) {
    ["posts"]=>
    array(1) {
      [0]=>
      array(1) {
        ["url"]=>
        string(23) "https://jobnet.dk/job/1"
      }
    }
  }
  ["input_tokens"]=>
  int(1200)
  ["output_tokens"]=>
  int(300)
}
string(10) "Find jobs."
array(1) {
  [0]=>
  array(3) {
    ["type"]=>
    string(10) "web_search"
    ["filters"]=>
    array(1) {
      ["allowed_domains"]=>
      array(1) {
        [0]=>
        string(9) "jobnet.dk"
      }
    }
    ["user_location"]=>
    array(2) {
      ["type"]=>
      string(11) "approximate"
      ["country"]=>
      string(2) "DK"
    }
  }
}
string(11) "json_schema"
