<?php
require_once __DIR__ . '/../Llm_adapter.php';
require_once __DIR__ . '/../Llm_web_search.php';
require_once __DIR__ . '/../Llm_exception.php';

/**
 * Child module llm/openai: OpenAI's Chat Completions API over plain HTTPS
 * (curl), loaded by the llm module, never from a URL. Uses a strict JSON
 * schema response format and reasoning_effort. Also works with any
 * OpenAI-compatible endpoint through `base_url`. web_search() uses the
 * Responses API with its web_search tool.
 */
class Openai extends Trongate implements Llm_adapter, Llm_web_search {

    private ?string $api_key = null;
    private string $model = 'gpt-5.4-mini';
    private string $base_url = 'https://api.openai.com/v1';
    private int $timeout = 180;

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        $this->parent_module = 'llm';
        block_url('llm-openai');
    }

    public function configure(array $config): void {
        $this->api_key = $config['api_key'] ?? null;
        $this->model = $config['model'] ?? $this->model;
        $this->base_url = $config['base_url'] ?? $this->base_url;
        $this->timeout = (int) ($config['timeout'] ?? $this->timeout);
    }

    public function structured(string $system, string $user, array $schema, string $effort = 'medium'): array {
        if (!$this->api_key) {
            throw new Llm_exception('OpenAI needs an API key, and PHP can\'t see OPENAI_API_KEY (or LLM_API_KEY) in its environment. Set it on the server; if it is set, the web server isn\'t passing it on to PHP (see .htaccess PassEnv).');
        }
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'reasoning_effort' => $effort,
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'answer', 'strict' => true, 'schema' => $schema],
            ],
        ];

        $response = $this->post('/chat/completions', $body);

        $choice = $response['choices'][0] ?? [];
        if (($choice['message']['refusal'] ?? null) !== null) {
            throw new Llm_exception('OpenAI declined: ' . $choice['message']['refusal']);
        }
        if (($choice['finish_reason'] ?? '') === 'length') {
            throw new Llm_exception('OpenAI ran out of tokens before finishing.');
        }
        $content = $choice['message']['content'] ?? null;
        if (!is_string($content) || $content === '') {
            throw new Llm_exception('OpenAI returned no answer.');
        }
        return json_decode($content, true, flags: JSON_THROW_ON_ERROR);
    }

    public function web_search(string $system, string $user, array $schema, array $domains = [], string $country = '', string $effort = 'low'): array {
        if (!$this->api_key) {
            throw new Llm_exception('OpenAI needs an API key, and PHP can\'t see OPENAI_API_KEY (or LLM_API_KEY) in its environment.');
        }
        $tool = ['type' => 'web_search'];
        if ($domains) {
            $tool['filters'] = ['allowed_domains' => array_values($domains)];
        }
        if ($country !== '') {
            $tool['user_location'] = ['type' => 'approximate', 'country' => $country];
        }
        $response = $this->post('/responses', [
            'model' => $this->model,
            'instructions' => $system,
            'input' => $user,
            'tools' => [$tool],
            'reasoning' => ['effort' => $effort],
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'answer', 'strict' => true, 'schema' => $schema]],
        ]);
        if (($response['status'] ?? '') === 'incomplete') {
            $why = $response['incomplete_details']['reason'] ?? 'unknown';
            throw new Llm_exception("OpenAI stopped before finishing ($why).", $why !== 'max_output_tokens');
        }
        $text = null;
        foreach ($response['output'] ?? [] as $item) {
            if (($item['type'] ?? '') !== 'message') {
                continue;
            }
            foreach ($item['content'] ?? [] as $part) {
                if (($part['type'] ?? '') === 'refusal') {
                    throw new Llm_exception('OpenAI declined: ' . ($part['refusal'] ?? ''));
                }
                if (($part['type'] ?? '') === 'output_text') {
                    $text = $part['text'] ?? null;
                }
            }
        }
        if (!is_string($text) || $text === '') {
            throw new Llm_exception('OpenAI returned no answer.');
        }
        return [
            'answer' => json_decode($text, true, flags: JSON_THROW_ON_ERROR),
            'input_tokens' => (int) ($response['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($response['usage']['output_tokens'] ?? 0),
        ];
    }

    /**
     * POSTs $body to the API at $path and returns the decoded answer.
     *
     * @throws Llm_exception When OpenAI can't be reached, times out or answers with an error.
     */
    private function post(string $path, array $body): array {
        $curl = curl_init(rtrim($this->base_url, '/') . $path);
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $this->api_key],
            CURLOPT_POSTFIELDS => json_encode($body, JSON_THROW_ON_ERROR),
        ]);
        $raw = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);
        $errno = curl_errno($curl);
        curl_close($curl);

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new Llm_exception("OpenAI didn't answer within {$this->timeout} seconds. Try again; if it keeps happening, try with less text.", true);
        }
        if ($raw === false) {
            throw new Llm_exception("OpenAI could not be reached: $error", true);
        }
        $response = json_decode($raw, true);
        if ($status === 401) {
            throw new Llm_exception('OpenAI rejected the API key.');
        }
        if ($status === 429) {
            throw new Llm_exception('OpenAI is rate limited right now. Try again in a minute.', true);
        }
        if ($status >= 400 || !is_array($response)) {
            $detail = $response['error']['message'] ?? "HTTP $status";
            throw new Llm_exception("OpenAI failed: $detail", $status >= 500);
        }
        return $response;
    }

}
