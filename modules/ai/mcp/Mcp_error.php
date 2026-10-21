<?php
/**
 * A JSON-RPC error inside Mcp_server: thrown where the message is found
 * wrong, and turned into the error reply in one place (Mcp_server::answer).
 * The code is JSON-RPC's (-32700 parse error, -32600 invalid request,
 * -32601 no such method, -32602 invalid params).
 */
class Mcp_error extends RuntimeException {

    public function __construct(int $code, string $message, public readonly string|int|null $id = null) {
        parent::__construct($message, $code);
    }

}
