<?php
/**
 * What a tool's handle() returns: text for the model to read, and
 * optionally the same as structured data. An error() is a tool error
 * (isError), not a protocol one, so the model reads it and can call again.
 *
 *   return Mcp_reply::text('3 added.', ['added' => 3]);
 *   return Mcp_reply::error("posts[0].url must be a string.");
 */
class Mcp_reply {

    private function __construct(private string $text, private ?array $structured, private bool $is_error) {
    }

    /** @throws InvalidArgumentException for a list: structured content is a JSON object */
    public static function text(string $text, ?array $structured = null): self {
        if ($structured !== null && $structured !== [] && array_is_list($structured)) {
            throw new InvalidArgumentException('Structured content must have keys (a JSON object), not be a list.');
        }
        return new self($text, $structured, false);
    }

    public static function error(string $text): self {
        return new self($text, null, true);
    }

    /** The tools/call result, as the MCP spec spells it. */
    public function to_array(): array {
        $result = ['content' => [['type' => 'text', 'text' => $this->text]]];
        if ($this->structured !== null) {
            $result['structuredContent'] = (object) $this->structured;
        }
        if ($this->is_error) {
            $result['isError'] = true;
        }
        return $result;
    }

}
