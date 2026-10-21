<?php
require_once __DIR__ . '/../ai/mcp/Mcp_tool.php';

/**
 * The add_job_links tool of job_scan/mcp/{token}: adds job posts to one
 * saved search through $add_posts, the same code job_scan/api runs,
 * answering ['added' => [...], 'skipped' => [...]]. The arguments reach
 * handle() already checked against schema(), field lengths as
 * scanned_job_posts keeps them; add_posts() still decides what a good link is.
 */
class Add_job_links_tool extends Mcp_tool {

    public function __construct(private Closure $add_posts, private string $search_url, private int $posts_per_call) {
    }

    public function name(): string {
        return 'add_job_links';
    }

    public function title(): string {
        return 'Add job links to the job search';
    }

    public function description(): string {
        return 'Adds job posts to the user\'s saved job search on Recruiter, where each one is read and matched with the search\'s CV. Send each post\'s own page, not a search results page. Posts the search has already are skipped. At most ' . $this->posts_per_call . ' posts per call.';
    }

    public function schema(): array {
        return [
            'type' => 'object',
            'required' => ['posts'],
            'properties' => ['posts' => ['type' => 'array', 'items' => [
                'type' => 'object',
                'required' => ['url'],
                'properties' => [
                    'url' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 2048, 'description' => "The job post's own page."],
                    'title' => ['type' => 'string', 'maxLength' => 255, 'description' => 'The job title.'],
                    'company' => ['type' => 'string', 'maxLength' => 255],
                    'location' => ['type' => 'string', 'maxLength' => 255],
                ],
                'additionalProperties' => false,
            ], 'minItems' => 1, 'maxItems' => $this->posts_per_call]],
            'additionalProperties' => false,
        ];
    }

    public function annotations(): array {
        return ['readOnlyHint' => false, 'destructiveHint' => false, 'idempotentHint' => true, 'openWorldHint' => true];
    }

    public function handle(array $arguments): Mcp_reply {
        $done = ($this->add_posts)($arguments['posts']);
        $text = count($done['added']) . ' added; they are matched in a moment on ' . $this->search_url . '.';
        foreach ($done['skipped'] as $skip) {
            $text .= "\nSkipped {$skip['url']}: {$skip['reason']}";
        }
        return Mcp_reply::text($text, ['added' => count($done['added']), 'skipped' => $done['skipped'], 'search' => $this->search_url]);
    }

}
