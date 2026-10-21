<?php
require_once __DIR__ . '/Mcp_request.php';
require_once __DIR__ . '/Mcp_server.php';

/**
 * Serves an MCP server (Mcp_server) from a module's own controller method,
 * for a client such as a ChatGPT connector:
 *
 *   public function mcp(): void {
 *       $search = ...;  // who may call: the module decides
 *       $this->module('ai-mcp');
 *       $this->mcp->_serve(new Mcp_server('recruiter-job-scan', '1.0.0', $instructions, [new Add_job_links_tool(...)]));
 *   }
 *
 * _serve() checks the request (Mcp_request), answers the body and sends the
 * reply: 200 with JSON, or 202 and nothing for a notification. _refuse()
 * sends a JSON-RPC error with an HTTP status, such as 404 for an unknown
 * link, so every server refuses the same way. Both end the request.
 */
class Mcp extends Trongate {

    // Browser origins allowed besides the app's own. ChatGPT calls from its
    // servers (no Origin), but its web client may send one.
    public const ORIGINS = ['https://chatgpt.com', 'https://chat.openai.com'];

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('ai-mcp');
    }

    public function _serve(Mcp_server $server): never {
        $request = Mcp_request::from_globals(Mcp_server::MAX_BODY);
        $refusal = $request->refusal(self::origins(), Mcp_server::PROTOCOL_VERSIONS);
        if ($refusal !== null) {
            if ($refusal[0] === 405) {
                header('Allow: POST');
            }
            if ($refusal[0] === 403) {
                error_log('mcp: refused a request from ' . $request->origin);
            }
            $this->_refuse($refusal[0], $refusal[1]);
        }
        $reply = $server->answer($request->body);
        if ($reply === null) {
            http_response_code(202);
            die();
        }
        header('Content-Type: application/json');
        echo json_encode($reply, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        die();
    }

    /** Ends the request with a JSON-RPC error (no id) and an HTTP status. */
    public function _refuse(int $status, string $message, int $code = -32600): never {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => $code, 'message' => $message]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        die();
    }

    /** @return list<string> */
    private static function origins(): array {
        return [Mcp_request::origin_of(BASE_URL), ...self::ORIGINS];
    }

}
