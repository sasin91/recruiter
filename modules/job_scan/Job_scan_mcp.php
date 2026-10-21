<?php
/**
 * The MCP (Model Context Protocol) side of job_scan/mcp/{token}: answers the
 * body of one request from an MCP client, such as a ChatGPT connector, with
 * the reply to send back, or null for a notification (nothing to send). The
 * body is checked as JSON-RPC 2.0 and the tool's arguments against its
 * inputSchema before anything is added. It has
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

    // Bytes of one request body: 50 posts with long links fit easily.
    public const MAX_BODY = 262144;

    // Characters of each post field, as scanned_job_posts keeps them.
    private const FIELD_LENGTHS = ['url' => 2048, 'title' => 255, 'company' => 255, 'location' => 255];

    /**
     * The reply to one request body holding a JSON-RPC message, or null
     * when it is a notification or a reply (no id, or no method).
     */
    public function answer(string $body): ?array {
        if (strlen($body) > self::MAX_BODY) {
            return self::error(null, -32600, 'The request is over ' . self::MAX_BODY . ' bytes. Send fewer posts at a time.');
        }
        try {
            $message = json_decode($body, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            return self::error(null, -32700, 'The body is not JSON: ' . $e->getMessage() . '.');
        }
        if (!is_array($message) || array_is_list($message) || ($message['jsonrpc'] ?? null) !== '2.0') {
            return self::error(null, -32600, 'Send one JSON-RPC 2.0 message (a batch is not taken).');
        }
        $id = $message['id'] ?? null;
        if (!is_string($id) && !is_int($id) && $id !== null) {
            return self::error(null, -32600, 'id must be a string or a number.');
        }
        $method = $message['method'] ?? null;
        if (!array_key_exists('id', $message) || !is_string($method)) {
            return null;
        }
        $params = $message['params'] ?? [];
        if (!is_array($params) || ($params !== [] && array_is_list($params))) {
            return self::error($id, -32602, 'params must be an object.');
        }
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

    /**
     * What is wrong with add_job_links' arguments, measured against its
     * inputSchema, one line each; empty when nothing is.
     *
     * @return list<string>
     */
    public function problems(mixed $arguments): array {
        $posts = is_array($arguments) ? ($arguments['posts'] ?? null) : null;
        if (!is_array($posts) || !array_is_list($posts) || $posts === []) {
            return ['Send posts as a list: {"posts": [{"url": "https://..."}]}.'];
        }
        if (count($posts) > $this->posts_per_call) {
            return ['At most ' . $this->posts_per_call . ' posts per call; send the rest in the next one.'];
        }
        $problems = [];
        foreach ($posts as $i => $post) {
            if (!is_array($post) || ($post !== [] && array_is_list($post))) {
                $problems[] = "posts[$i] must be an object.";
                continue;
            }
            foreach (array_diff(array_keys($post), array_keys(self::FIELD_LENGTHS)) as $key) {
                $problems[] = "posts[$i].$key is not a field (url, title, company, location).";
            }
            foreach (self::FIELD_LENGTHS as $key => $max) {
                $value = $post[$key] ?? null;
                if ($value === null && $key !== 'url') {
                    continue;
                }
                if (!is_string($value) || ($key === 'url' && trim($value) === '')) {
                    $problems[] = "posts[$i].$key must be " . ($key === 'url' ? 'the post\'s link.' : 'text.');
                } elseif (mb_strlen($value, 'UTF-8') > $max) {
                    $problems[] = "posts[$i].$key is over $max characters.";
                }
            }
        }
        return $problems;
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
                        'url' => ['type' => 'string', 'maxLength' => 2048, 'description' => "The job post's own page."],
                        'title' => ['type' => 'string', 'maxLength' => 255, 'description' => 'The job title.'],
                        'company' => ['type' => 'string', 'maxLength' => 255],
                        'location' => ['type' => 'string', 'maxLength' => 255],
                    ],
                    'additionalProperties' => false,
                ], 'minItems' => 1, 'maxItems' => $this->posts_per_call]],
                'additionalProperties' => false,
            ],
            'annotations' => ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => false],
        ];
    }

    private function call(mixed $id, array $params): array {
        if (($params['name'] ?? null) !== 'add_job_links') {
            return self::error($id, -32602, 'No tool ' . (is_string($params['name'] ?? null) ? $params['name'] : '') . '; the one tool is add_job_links.');
        }
        $problems = $this->problems($params['arguments'] ?? null);
        if ($problems) {
            // A tool error, not a protocol one, so the model reads it and can try again.
            return self::result($id, ['content' => [['type' => 'text', 'text' => implode("\n", $problems)]], 'isError' => true]);
        }
        $done = ($this->add_posts)($params['arguments']['posts']);
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
