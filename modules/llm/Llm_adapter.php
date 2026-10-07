<?php
/**
 * One structured-output request to a language model, whatever the provider.
 * Each provider is a child module of llm (llm/anthropic, llm/openai) that
 * implements this; callers go through the llm module, so switching provider
 * or model is a config change (config/llm.php).
 */
interface Llm_adapter {

    /**
     * @param array $config { model?, api_key?, base_url? } from config/llm.php
     */
    public function configure(array $config): void;

    /**
     * Asks the model for one JSON answer that follows `schema`.
     *
     * @param string $system The instructions.
     * @param string $user The text to read.
     * @param array $schema JSON schema of the answer (objects closed with
     *   additionalProperties: false, every property required).
     * @param string $effort How hard to think: low, medium or high.
     * @return array The decoded answer.
     * @throws Llm_exception When the provider fails, declines or returns no answer.
     */
    public function structured(string $system, string $user, array $schema, string $effort = 'medium'): array;

}
