<?php
// Language model settings for the llm module. Environment variables
// LLM_PROVIDER, LLM_MODEL and LLM_API_KEY override these values.
// The API key stays out of git: set OPENAI_API_KEY (or LLM_API_KEY) on
// the server.
return [
    'provider' => 'openai',    // openai | anthropic
    'model' => 'gpt-5.4-mini', // the extraction bake-off's pick; e.g. claude-opus-5-5 for anthropic
    'api_key_env' => 'OPENAI_API_KEY', // read by the llm module; ANTHROPIC_API_KEY for anthropic
    // 'base_url' => 'https://api.openai.com/v1', // openai: any compatible endpoint
];
