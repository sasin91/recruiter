<?php
/**
 * The provider's HTTP answer: status, headers and body. Every Llm_exception
 * thrown after an answer carries it, so a log (and the user) can see why
 * the request failed, such as which rate limit a 429 hit and when it resets,
 * or what a refused or cut-short answer said.
 */
class Llm_response {

    /** How much of the body a log line keeps. */
    private const LOGGED_BODY_CHARACTERS = 2000;

    /** Headers worth a log line: rate limits, when to retry and the request id to quote to the provider. */
    private const LOGGED_HEADER_PATTERN = '~^(retry-after|x-ratelimit-|anthropic-ratelimit-|x-request-id$|request-id$)~';

    /** An API key as it may appear in an error body ("Incorrect API key provided: sk-ab...yz"). */
    private const API_KEY_PATTERN = '~\b(sk-(?:ant-)?)[A-Za-z0-9_\-*.]{4,}~';

    public readonly string $body;

    /**
     * @param array<string, string> $headers lower-case name => value
     */
    public function __construct(public readonly int $status, public readonly array $headers, string $body) {
        $this->body = preg_replace(self::API_KEY_PATTERN, '$1…', $body);
    }

    /** From a PSR-7 response, as the Anthropic SDK gives it. */
    public static function from_psr(\Psr\Http\Message\ResponseInterface $response): self {
        $headers = [];
        foreach (array_keys($response->getHeaders()) as $name) {
            $headers[strtolower($name)] = $response->getHeaderLine($name);
        }
        return new self($response->getStatusCode(), $headers, (string) $response->getBody());
    }

    /** The body as decoded JSON, or [] when it isn't. */
    public function json(): array {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : [];
    }

    /** The provider's own words on what went wrong (error.message), or ''. */
    public function error_message(): string {
        return trim((string) ($this->error()['message'] ?? ''));
    }

    /** The provider's error code or type ("rate_limit_exceeded", "insufficient_quota"), or ''. */
    public function error_code(): string {
        $error = $this->error();
        return (string) ($error['code'] ?? $error['type'] ?? '');
    }

    /** One line for the server log: status, the headers that explain it, and the body. */
    public function summary(): string {
        $parts = ["HTTP {$this->status}"];
        foreach ($this->headers as $name => $value) {
            if (preg_match(self::LOGGED_HEADER_PATTERN, $name)) {
                $parts[] = "$name: $value";
            }
        }
        $parts[] = 'body: ' . $this->logged_body();
        return implode('; ', $parts);
    }

    private function error(): array {
        $error = $this->json()['error'] ?? null;
        return is_array($error) ? $error : [];
    }

    private function logged_body(): string {
        $body = preg_replace('~\s+~', ' ', trim($this->body));
        return mb_strlen($body) > self::LOGGED_BODY_CHARACTERS
            ? mb_substr($body, 0, self::LOGGED_BODY_CHARACTERS) . '…'
            : $body;
    }

}
