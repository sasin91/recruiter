<?php
/**
 * An adapter whose model can search the web while it answers (llm/openai,
 * through the Responses API's web_search tool).
 */
interface Llm_web_search {

    /**
     * Asks the model for one JSON answer that follows `schema`, searching
     * the web for it.
     *
     * @param string[] $domains Sites to search only (["jobnet.dk"]); [] for any.
     * @param string $country Two-letter country the search is made from ("DK").
     * @return array{answer: array, input_tokens: int, output_tokens: int}
     * @throws Llm_exception When the provider fails, declines or returns no answer.
     */
    public function web_search(string $system, string $user, array $schema, array $domains = [], string $country = '', string $effort = 'low'): array;

}
