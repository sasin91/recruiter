--TEST--
Mcp_schema names each problem by path, and lists keywords it doesn't check
--FILE--
<?php
require __DIR__ . '/../Mcp_schema.php';
$posts = ['type' => 'object', 'required' => ['posts'], 'additionalProperties' => false, 'properties' => [
    'posts' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 3, 'items' => [
        'type' => 'object', 'required' => ['url'], 'additionalProperties' => false, 'properties' => [
            'url' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 20],
            'title' => ['type' => 'string', 'maxLength' => 5, 'description' => 'The job title.'],
            'kind' => ['enum' => ['full', 'part']],
            'hours' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 40],
            'remote' => ['type' => 'boolean'],
        ],
    ]],
]];
$show = fn(array $problems) => print(($problems === [] ? 'ok' : implode(' | ', $problems)) . "\n");

$show(Mcp_schema::problems($posts, ['posts' => [['url' => 'https://a', 'title' => 'Dev', 'kind' => 'full', 'hours' => 37, 'remote' => true]]]));
$show(Mcp_schema::problems($posts, []));
$show(Mcp_schema::problems($posts, 'x'));
$show(Mcp_schema::problems($posts, ['posts' => 'x']));
$show(Mcp_schema::problems($posts, ['posts' => []]));
$show(Mcp_schema::problems($posts, ['posts' => [['url' => 'a'], ['url' => 'b'], ['url' => 'c'], ['url' => 'd']]]));
$show(Mcp_schema::problems($posts, ['posts' => ['https://a', ['url' => ''], ['title' => 5, 'salary' => '1']], 'extra' => 1]));
$show(Mcp_schema::problems($posts, ['posts' => [['url' => str_repeat('x', 21), 'title' => 'Udvikler', 'kind' => 'some', 'hours' => 50, 'remote' => 'yes']]]));
$show(Mcp_schema::problems($posts, ['posts' => [['url' => 'æøåæøåæøåæøåæøåæøåæø', 'hours' => 0.5]]]));
$show(Mcp_schema::problems(['type' => 'number', 'minimum' => 0], 2.5));
$show(Mcp_schema::problems(['type' => 'array'], ['a' => 1]));
$show(Mcp_schema::problems(['type' => 'integer'], 3.0));
$show(Mcp_schema::problems(['type' => 'integer'], 3.5));
$show(Mcp_schema::problems(['enum' => [1, 2]], 1.0));
$show(Mcp_schema::problems(['enum' => [['a' => 1, 'b' => [1, 2]]]], ['b' => [1, 2.0], 'a' => 1]));
$show(Mcp_schema::problems(['enum' => [['a' => 1]]], ['a' => '1']));

echo "-- unsupported\n";
$show(Mcp_schema::unsupported($posts));
$show(Mcp_schema::unsupported(['type' => 'object', 'additionalProperties' => ['type' => 'string'], 'properties' => ['a' => ['type' => 'string', 'pattern' => '^x'], 'b' => ['type' => 'array', 'items' => ['oneOf' => []]]]]));
?>
--EXPECT--
ok
posts is missing.
arguments must be an object.
posts must be a list.
posts must have at least 1 item.
posts has 4 items; at most 3 per call, so send the rest in the next one.
extra is not a field (posts). | posts[0] must be an object. | posts[1].url must not be empty. | posts[2].url is missing. | posts[2].salary is not a field (url, title, kind, hours, remote). | posts[2].title must be a string.
posts[0].url is over 20 characters. | posts[0].title is over 5 characters. | posts[0].kind must be one of: "full", "part". | posts[0].hours must be at most 40. | posts[0].remote must be a boolean.
posts[0].hours must be a whole number.
ok
arguments must be a list.
ok
arguments must be a whole number.
ok
ok
arguments must be one of: {"a":1}.
-- unsupported
ok
arguments: additionalProperties other than false | a: pattern | b[]: oneOf
