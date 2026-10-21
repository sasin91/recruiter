# AI

Version 0.1.0

Pieces a Trongate app needs to work with AI clients, as child modules. Nothing here is app code, so the folder can be copied into another Trongate app unchanged.

| Child module | What it is |
|---|---|
| `ai/mcp` (`ai-mcp`) | Serves MCP (Model Context Protocol) tools from any module, for clients such as a ChatGPT connector. |

The language model adapters are still in `modules/llm`.

## ai/mcp

A module offers tools to an MCP client by writing one class per tool and serving them from one of its own controller methods. The protocol, the checks of the request and of each call's arguments, and the errors are here; the module decides who may call and what its tools do.

### A tool

```php
// modules/job_scan/Add_job_links_tool.php
require_once __DIR__ . '/../ai/mcp/Mcp_tool.php';

class Add_job_links_tool extends Mcp_tool {

    public function __construct(private Closure $add_posts) {
    }

    public function name(): string {
        return 'add_job_links';
    }

    public function description(): string {
        return "Adds job posts to the user's saved job search.";
    }

    public function schema(): array {
        return ['type' => 'object', 'required' => ['posts'], 'additionalProperties' => false, 'properties' => [
            'posts' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => [
                'type' => 'object', 'required' => ['url'], 'additionalProperties' => false, 'properties' => [
                    'url' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048],
                ],
            ]],
        ]];
    }

    public function handle(array $arguments): Mcp_reply {
        $added = ($this->add_posts)($arguments['posts']);
        return Mcp_reply::text("$added added.", ['added' => $added]);
    }

}
```

`handle()` gets arguments already checked against `schema()`, so the schema the client sees and the check the server runs are one definition. When they don't fit, the client gets a tool error (`isError`) listing each problem by path (`posts[2].url is over 2048 characters.`), which the model reads and can fix. A tool returns `Mcp_reply::error()` for its own refusals; when `handle()` throws, the error is logged and the client gets a tool error saying so.

`schema()` is checked by `Mcp_schema`, a JSON Schema subset: `type`, `required`, `properties`, `additionalProperties: false`, `items`, `enum`, `minLength`/`maxLength`, `minItems`/`maxItems`, `minimum`/`maximum` (plus `title` and `description`). Put `Mcp_schema::unsupported($tool->schema())` in the tool's test, so a keyword outside the subset is caught there.

Optional: `title()` (a name for people) and `annotations()` (`readOnlyHint`, `destructiveHint`, `idempotentHint`, `openWorldHint`).

### Serving it

```php
// modules/job_scan/Job_scan.php
public function mcp(): void {
    $search = $this->model->by_api_token_hash(hash('sha256', (string) segment(3)));
    $this->module('ai-mcp');
    if ($search === null) {
        $this->mcp->_refuse(404, 'Unknown link.', -32001);
    }
    $this->mcp->_serve(new Mcp_server('recruiter-job-scan', '1.0.0', 'Send the job posts you find to add_job_links.', [
        new Add_job_links_tool(fn(array $posts) => $this->add_posts((int) $search['id'], $posts)),
    ]));
}
```

Who may call is the serving method's business, before it makes the tools: here a secret token in the link, since a ChatGPT connector signs in with OAuth or not at all. The tools get what they need (the search) in their constructor, so the server knows nothing of users or tokens.

`_serve()` answers one request and ends it:

| Check | Answer |
|---|---|
| Not POST (there is no SSE stream) | 405, `Allow: POST` |
| `Origin` sent, and not the app's own or in `Mcp::ORIGINS` (chatgpt.com) | 403, logged |
| `Content-Type` not `application/json` | 415 |
| `MCP-Protocol-Version` sent, and not one in `Mcp_server::PROTOCOL_VERSIONS` | 400 |
| Body over `Mcp_server::MAX_BODY` (256 KB) | -32600 |
| Body not JSON | -32700 |
| Not one JSON-RPC 2.0 object (a batch, a bad `id`, `params` not an object) | -32600 / -32602 |
| No such method | -32601 (only `initialize`, `ping`, `tools/list`, `tools/call`) |
| No such tool, or `arguments` not an object | -32602 |
| A notification, or a reply from the client | 202, no body |
| Anything else failing | -32603, logged |

### Files

| File | What it is |
|---|---|
| `Mcp.php` | The controller: `_serve()` and `_refuse()`. The only file that needs the Trongate engine. |
| `Mcp_server.php` | Answers one JSON-RPC message. No HTTP, so tests call `answer()` with a string. |
| `Mcp_request.php` | The HTTP checks above. |
| `Mcp_tool.php`, `Mcp_reply.php` | What a module writes, and what its tools return. |
| `Mcp_schema.php` | The argument check. |
| `Mcp_error.php` | A JSON-RPC error inside the server. |

Tests: `php bin/run-tests.php modules/ai`.

### Later: OAuth

When connectors sign in with OAuth, a child module `ai/oauth` will be the authorization server for every MCP server in the app, and a serving method will ask it for the user instead of reading a token from the link. That needs new tables, which are proposed separately first.
