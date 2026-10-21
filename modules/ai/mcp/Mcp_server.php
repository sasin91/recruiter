<?php
require_once __DIR__ . '/Mcp_error.php';
require_once __DIR__ . '/Mcp_reply.php';
require_once __DIR__ . '/Mcp_schema.php';
require_once __DIR__ . '/Mcp_tool.php';

/**
 * An MCP (Model Context Protocol) tool server: answers the body of one
 * request from an MCP client, such as a ChatGPT connector, with the reply to
 * send back, or null for a notification or a reply (nothing to send).
 *
 *   $server = new Mcp_server('recruiter-job-scan', '1.0.0', 'Send the job posts you find to add_job_links.', [
 *       new Add_job_links_tool(...),
 *   ]);
 *   $reply = $server->answer($body);
 *
 * Only what a tool server needs: initialize, ping, tools/list and
 * tools/call, with plain JSON replies (no SSE stream). The body is checked
 * as one JSON-RPC 2.0 message, and a call's arguments against its tool's
 * schema() before the tool runs. It knows nothing of HTTP or of who is
 * calling: Mcp (the controller) checks the request, and the module serving
 * it decides who may call before it makes the tools.
 */
class Mcp_server {

    // Newest first; a client asking for another version gets the first.
    public const PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

    // Bytes of one request body.
    public const MAX_BODY = 262144;

    /** @var array<string, Mcp_tool> by name */
    private array $tools = [];

    /** @param list<Mcp_tool> $tools */
    public function __construct(private string $name, private string $version, private string $instructions, array $tools) {
        foreach ($tools as $tool) {
            $this->tools[$tool->name()] = $tool;
        }
    }

    /** The reply to one request body, or null when there is nothing to send. */
    public function answer(string $body): ?array {
        $message = null;
        try {
            $message = self::message($body);
            if (!is_string($message['method'] ?? null) || !array_key_exists('id', $message)) {
                return null;
            }
            return self::result($message['id'], $this->dispatch($message['id'], $message['method'], self::params($message)));
        } catch (Mcp_error $e) {
            return self::error($e->id, $e->getCode(), $e->getMessage());
        } catch (Throwable $e) {
            error_log("mcp: {$this->name} failed: " . $e->getMessage());
            return self::error($message['id'] ?? null, -32603, 'The server failed, and the error is logged. Try again later.');
        }
    }

    /** @return array<string, Mcp_tool> */
    public function tools(): array {
        return $this->tools;
    }

    private function dispatch(string|int|null $id, string $method, array $params): array|object {
        return match ($method) {
            'initialize' => $this->initialize($params),
            'ping' => (object) [],
            'tools/list' => ['tools' => array_values(array_map(fn(Mcp_tool $tool) => $tool->definition(), $this->tools))],
            'tools/call' => $this->call($id, $params),
            default => throw new Mcp_error(-32601, "No method $method.", $id),
        };
    }

    private function initialize(array $params): array {
        $asked = $params['protocolVersion'] ?? null;
        $result = [
            'protocolVersion' => in_array($asked, self::PROTOCOL_VERSIONS, true) ? $asked : self::PROTOCOL_VERSIONS[0],
            'capabilities' => ['tools' => (object) []],
            'serverInfo' => ['name' => $this->name, 'version' => $this->version],
        ];
        if ($this->instructions !== '') {
            $result['instructions'] = $this->instructions;
        }
        return $result;
    }

    private function call(string|int|null $id, array $params): array {
        $name = $params['name'] ?? null;
        $tool = is_string($name) ? ($this->tools[$name] ?? null) : null;
        if ($tool === null) {
            throw new Mcp_error(-32602, 'No tool ' . (is_string($name) ? $name : '') . '; the tools are: ' . implode(', ', array_keys($this->tools)) . '.', $id);
        }
        $arguments = $params['arguments'] ?? [];
        if (!is_array($arguments) || ($arguments !== [] && array_is_list($arguments))) {
            throw new Mcp_error(-32602, 'arguments must be an object.', $id);
        }
        $problems = Mcp_schema::problems($tool->schema(), $arguments);
        if ($problems !== []) {
            return Mcp_reply::error(implode("\n", $problems))->to_array();
        }
        try {
            return $tool->handle($arguments)->to_array();
        } catch (Throwable $e) {
            error_log("mcp: the {$this->name} tool {$tool->name()} failed: " . $e->getMessage());
            return Mcp_reply::error("{$tool->name()} failed on the server, and the error is logged. Try again later.")->to_array();
        }
    }

    /** The body as one JSON-RPC 2.0 message, with a usable id. */
    private static function message(string $body): array {
        if (strlen($body) > self::MAX_BODY) {
            throw new Mcp_error(-32600, 'The request is over ' . self::MAX_BODY . ' bytes.');
        }
        try {
            $message = json_decode($body, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new Mcp_error(-32700, 'The body is not JSON: ' . $e->getMessage() . '.');
        }
        if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            throw new Mcp_error(-32600, 'Send one JSON-RPC 2.0 message (a batch is not taken).');
        }
        $id = $message['id'] ?? null;
        if (!is_string($id) && !is_int($id) && $id !== null) {
            throw new Mcp_error(-32600, 'id must be a string or a number.');
        }
        return $message;
    }

    private static function params(array $message): array {
        $params = $message['params'] ?? [];
        if (!is_array($params) || ($params !== [] && array_is_list($params))) {
            throw new Mcp_error(-32602, 'params must be an object.', $message['id']);
        }
        return $params;
    }

    private static function result(string|int|null $id, array|object $result): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private static function error(string|int|null $id, int $code, string $message): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

}
