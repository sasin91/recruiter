<?php
/**
 * The MCP (Model Context Protocol) side of job_scan/mcp/{token}: answers one
 * JSON-RPC message from an MCP client, such as a ChatGPT connector, with the
 * reply to send back, or null for a notification (nothing to send). It has
 * one tool, add_job_links, which hands the posts to $add_posts: the same
 * code job_scan/api runs, answering ['added' => [...], 'skipped' => [...]].
 *
 * Only what a tool server needs: initialize, ping, tools/list and
 * tools/call, with plain JSON replies (no SSE stream).
 */
class Job_scan_mcp {

    // Newest first; a client asking for another version gets the first.
    public const PROTOCOL_VERSIONS = ['2025-11-25', '2025-06-18', '2025-03-26', '2024-11-05'];

    public function __construct(private Closure $add_posts, private string $search_url, private int $posts_per_call) {
    }

    /**
     * The reply to one JSON-RPC message, or null when it is a notification
     * or a reply (no id, or no method).
     */
    public function answer(mixed $message): ?array {
        if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, -32600, 'Send one JSON-RPC 2.0 message.');
        }
        $id = $message['id'] ?? null;
        $method = $message['method'] ?? null;
        if (!array_key_exists('id', $message) || !is_string($method)) {
            return null;
        }
        $params = is_array($message['params'] ?? null) ? $message['params'] : [];
        return match ($method) {
            'initialize' => self::result($id, [
                'protocolVersion' => in_array($params['protocolVersion'] ?? null, self::PROTOCOL_VERSIONS, true) ? $params['protocolVersion'] : self::PROTOCOL_VERSIONS[0],
                'capabilities' => ['tools' => (object) []],
                'serverInfo' => ['name' => 'recruiter-job-scan', 'version' => '1.0.0'],
                'instructions' => 'Send the job posts you find to add_job_links. Each one is added to the user\'s saved job search on Recruiter and matched with its CV there.',
            ]),
            'ping' => self::result($id, (object) []),
            'tools/list' => self::result($id, ['tools' => [$this->tool()]]),
            'tools/call' => $this->call($id, $params),
            default => self::error($id, -32601, "No method $method."),
        };
    }

    /** The add_job_links tool, as tools/list describes it. */
    public function tool(): array {
        return [
            'name' => 'add_job_links',
            'title' => 'Add job links to the job search',
            'description' => 'Adds job posts to the user\'s saved job search on Recruiter, where each one is read and matched with the search\'s CV. Send each post\'s own page, not a search results page. Posts the search has already are skipped. At most ' . $this->posts_per_call . ' posts per call.',
            'inputSchema' => [
                'type' => 'object',
                'required' => ['posts'],
                'properties' => ['posts' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'required' => ['url'],
                    'properties' => [
                        'url' => ['type' => 'string', 'description' => "The job post's own page."],
                        'title' => ['type' => 'string', 'description' => 'The job title.'],
                        'company' => ['type' => 'string'],
                        'location' => ['type' => 'string'],
                    ],
                ]]],
            ],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ];
    }

    private function call(mixed $id, array $params): array {
        if (($params['name'] ?? null) !== 'add_job_links') {
            return self::error($id, -32602, 'No tool ' . (is_string($params['name'] ?? null) ? $params['name'] : '') . '; the one tool is add_job_links.');
        }
        $posts = $params['arguments']['posts'] ?? null;
        if (!is_array($posts) || !array_is_list($posts)) {
            return self::result($id, ['content' => [['type' => 'text', 'text' => 'Send posts as a list: {"posts": [{"url": "https://..."}]}.']], 'isError' => true]);
        }
        $done = ($this->add_posts)($posts);
        $text = count($done['added']) . ' added; they are matched in a moment on ' . $this->search_url . '.';
        foreach ($done['skipped'] as $skip) {
            $text .= "\nSkipped {$skip['url']}: {$skip['reason']}";
        }
        return self::result($id, [
            'content' => [['type' => 'text', 'text' => $text]],
            'structuredContent' => ['added' => count($done['added']), 'skipped' => $done['skipped'], 'search' => $this->search_url],
        ]);
    }

    private static function result(mixed $id, array|object $result): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    private static function error(mixed $id, int $code, string $message): array {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => $code, 'message' => $message]];
    }

}
