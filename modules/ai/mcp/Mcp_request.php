<?php
/**
 * One HTTP request to an MCP server, and what is wrong with it before its
 * body is read as JSON-RPC (Streamable HTTP, without the SSE stream):
 *
 * - POST only (405): the replies come back on the POST, so GET has no stream to open.
 * - Origin, when a browser sends one, is the app's own or an allowed client (403).
 *   The spec asks for this so a web page can't reach a server on localhost.
 * - Content-Type is application/json (415).
 * - MCP-Protocol-Version, when sent, is one the server speaks (400).
 *
 * refusal() answers [HTTP status, message], or null when the request is fine.
 */
class Mcp_request {

    public function __construct(
        public readonly string $method,
        public readonly string $content_type,
        public readonly string $origin,
        public readonly string $protocol_version,
        public readonly string $body,
    ) {
    }

    /** This request; the body is read up to one byte over $max_body, which is enough to refuse it. */
    public static function from_globals(int $max_body): self {
        return new self(
            (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            (string) ($_SERVER['CONTENT_TYPE'] ?? ''),
            (string) ($_SERVER['HTTP_ORIGIN'] ?? ''),
            (string) ($_SERVER['HTTP_MCP_PROTOCOL_VERSION'] ?? ''),
            (string) file_get_contents('php://input', false, null, 0, $max_body + 1),
        );
    }

    /**
     * @param list<string> $origins allowed, as scheme://host[:port]
     * @param list<string> $versions protocol versions the server speaks
     * @return array{0: int, 1: string}|null
     */
    public function refusal(array $origins, array $versions): ?array {
        if ($this->method !== 'POST') {
            return [405, 'Send JSON-RPC messages with POST.'];
        }
        if ($this->origin !== '' && !in_array(self::origin_of($this->origin), $origins, true)) {
            return [403, "Requests from {$this->origin} are not taken."];
        }
        if (!str_starts_with(strtolower(trim($this->content_type)), 'application/json')) {
            return [415, 'Send JSON (Content-Type: application/json).'];
        }
        if ($this->protocol_version !== '' && !in_array($this->protocol_version, $versions, true)) {
            return [400, "MCP protocol version {$this->protocol_version} is not spoken here; use one of " . implode(', ', $versions) . '.'];
        }
        return null;
    }

    /** scheme://host[:port] of a URL, lowercased; '' when it has none. */
    public static function origin_of(string $url): string {
        $parts = parse_url(strtolower(trim($url)));
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return '';
        }
        return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '');
    }

}
