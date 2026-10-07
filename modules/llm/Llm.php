<?php
require_once __DIR__ . '/Llm_adapter.php';
require_once __DIR__ . '/Llm_exception.php';

/**
 * Language models behind one call, whatever the provider. Each provider is a
 * child module (llm/anthropic, llm/openai) implementing Llm_adapter; this
 * parent picks one from config/llm.php (Claude by default). Environment
 * variables LLM_PROVIDER, LLM_MODEL and LLM_API_KEY override the file.
 *
 *   $this->module('llm');
 *   $answer = $this->llm->structured($system, $text, $schema, 'low');
 *
 * A user's own key replaces the server's settings for the request:
 *
 *   $this->llm->use_key('anthropic', $api_key, '');
 *
 * A new provider is one child module implementing Llm_adapter.
 */
class Llm extends Trongate {

    // The model a user's key runs on when they don't name one: the
    // extraction bake-off's OpenAI pick, and a Claude model that takes the
    // effort setting the anthropic adapter sends.
    public const USER_DEFAULT_MODELS = ['openai' => 'gpt-5.4-mini', 'anthropic' => 'claude-sonnet-5-5'];

    /** A user's provider, key and model, used instead of config/llm.php. */
    private ?array $user_config = null;

    public function __construct(?string $module_name = null) {
        parent::__construct($module_name);
        block_url('llm');
    }

    /**
     * One JSON answer that follows `schema`, from the configured provider.
     *
     * @throws Llm_exception
     */
    public function structured(string $system, string $user, array $schema, string $effort = 'medium'): array {
        return $this->adapter()->structured($system, $user, $schema, $effort);
    }

    /**
     * Runs this request's calls on a user's own key instead of the server's
     * settings. Nothing from config/llm.php carries over (no base_url), so
     * the key only goes to its provider.
     */
    public function use_key(string $provider, string $api_key, string $model = ''): void {
        if (!isset(self::USER_DEFAULT_MODELS[$provider])) {
            throw new Llm_exception("Unknown LLM provider \"$provider\".");
        }
        $this->user_config = [
            'provider' => $provider,
            'api_key' => $api_key,
            'model' => $model !== '' ? $model : self::USER_DEFAULT_MODELS[$provider],
        ];
    }

    /** The configured provider's child module, configured. */
    public function adapter(): Llm_adapter {
        $config = $this->user_config ?? self::config();
        $provider = strtolower($config['provider'] ?? 'anthropic');
        if (!preg_match('/^[a-z0-9_]+$/', $provider) || !is_file(__DIR__ . "/$provider/" . ucfirst($provider) . '.php')) {
            throw new Llm_exception("Unknown LLM provider \"$provider\" in config/llm.php.");
        }
        $this->module("llm-$provider");
        $adapter = $this->$provider;
        if (!$adapter instanceof Llm_adapter) {
            throw new Llm_exception("The llm/$provider module is not an Llm_adapter.");
        }
        $adapter->configure($config);
        return $adapter;
    }

    private static function config(): array {
        $file = __DIR__ . '/../../config/llm.php';
        $config = is_file($file) ? (require $file) : [];
        $config = is_array($config) ? $config : [];
        foreach (['provider' => 'LLM_PROVIDER', 'model' => 'LLM_MODEL', 'api_key' => 'LLM_API_KEY'] as $key => $env) {
            $value = self::env($env);
            if ($value !== null) {
                $config[$key] = $value;
            }
        }
        // The key named by api_key_env (OPENAI_API_KEY, ANTHROPIC_API_KEY).
        if (empty($config['api_key']) && !empty($config['api_key_env'])) {
            $config['api_key'] = self::env($config['api_key_env']);
        }
        return $config;
    }

    /**
     * An environment variable, wherever the server put it: the process
     * environment, or the request's server variables (Apache PassEnv/SetEnv,
     * PHP-FPM env[] or FastCGI params), which is where a web server hands
     * them to PHP when it doesn't pass its own environment on.
     */
    public static function env(string $name): ?string {
        foreach ([getenv($name), getenv($name, true), $_SERVER[$name] ?? null, $_ENV[$name] ?? null,
            $_SERVER["REDIRECT_$name"] ?? null] as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }
        return null;
    }

}
