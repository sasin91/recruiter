<?php
require_once __DIR__ . '/../Llm_adapter.php';
require_once __DIR__ . '/../Llm_exception.php';

/**
 * Child module llm/anthropic: Claude through the official Anthropic PHP SDK
 * (composer install puts it in packages/). Loaded by the llm module, never
 * from a URL. Structured output via output_config.format, thinking depth via
 * output_config.effort, and server-side refusal fallbacks enabled.
 */
class Anthropic extends Trongate implements Llm_adapter {

    private ?string $api_key = null;
    private string $model = 'claude-opus-5-5';
    private ?string $base_url = null;

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        $this->parent_module = 'llm';
        block_url('llm-anthropic');
    }

    public function configure(array $config): void {
        $this->api_key = $config['api_key'] ?? null;
        $this->model = $config['model'] ?? $this->model;
        $this->base_url = $config['base_url'] ?? $this->base_url;
    }

    public function structured(string $system, string $user, array $schema, string $effort = 'medium'): array {
        $autoload = __DIR__ . '/../../../packages/autoload.php';
        if (!is_file($autoload)) {
            throw new Llm_exception('The Anthropic SDK is not installed: run composer install.');
        }
        require_once $autoload;

        try {
            $client = new \Anthropic\Client(apiKey: $this->api_key, baseUrl: $this->base_url);
            // The raw call, so the response (headers, body) can go on any Llm_exception below.
            $raw = $client->beta->messages->raw->create(params: [
                'model' => $this->model,
                'maxTokens' => 16000,
                'system' => $system,
                'messages' => [['role' => 'user', 'content' => $user]],
                'outputConfig' => [
                    'effort' => $effort,
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                'fallbacks' => 'default',
                'betas' => ['server-side-fallback-2026-07-01'],
            ]);
            $message = $raw->parse();
        } catch (\Anthropic\Core\Exceptions\APIStatusException $e) {
            throw self::failed($e);
        }

        $answer = Llm_response::from_psr($raw);
        if ($message->stopReason === 'refusal') {
            throw new Llm_exception('Claude declined: ' . ($message->stopDetails?->explanation ?? 'no reason given'), false, null, $answer);
        }
        if ($message->stopReason === 'max_tokens') {
            throw new Llm_exception('Claude ran out of tokens before finishing.', false, null, $answer);
        }
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                return self::decoded($block->text, $answer);
            }
        }
        throw new Llm_exception('Claude returned no answer.', false, null, $answer);
    }

    /**
     * The model's JSON answer, or an Llm_exception carrying the response
     * when it isn't JSON.
     */
    private static function decoded(string $text, Llm_response $answer): array {
        $decoded = json_decode($text, true);
        if (!is_array($decoded)) {
            throw new Llm_exception('Claude answered with something other than the JSON asked for.', true, null, $answer);
        }
        return $decoded;
    }

    /** The SDK's status error as an Llm_exception carrying Claude's response. */
    private static function failed(\Anthropic\Core\Exceptions\APIStatusException $e): Llm_exception {
        $answer = $e->response === null ? null : Llm_response::from_psr($e->response);
        $detail = $answer?->error_message() ?? '';
        if ($e instanceof \Anthropic\Core\Exceptions\AuthenticationException) {
            $message = 'Claude rejected the API key, or none was given (ANTHROPIC_API_KEY or LLM_API_KEY on the server)';
            return new Llm_exception($detail !== '' ? "$message: $detail" : "$message.", false, $e, $answer);
        }
        if ($e instanceof \Anthropic\Core\Exceptions\RateLimitException) {
            $message = $detail !== '' ? "Claude rate limited the request: $detail" : 'Claude rate limited the request. Try again in a minute.';
            return new Llm_exception($message, true, $e, $answer);
        }
        $message = 'Claude failed: ' . ($detail !== '' ? $detail : "HTTP {$e->status}");
        return new Llm_exception($message, ($e->status ?? 0) >= 500, $e, $answer);
    }

}
