<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Chat Provider
    |--------------------------------------------------------------------------
    |
    | Provider untuk WMS Agent Chat widget (admin panel).
    | Pilihan: deepseek | chatai
    |
    */

    'provider' => strtolower((string) env('AI_PROVIDER', 'deepseek')),

    'providers' => [

        'deepseek' => [
            'label' => 'DeepSeek',
            'enabled' => (bool) env('DEEPSEEK_ENABLED', true),
            'api_key' => env('DEEPSEEK_API_KEY'),
            'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),
            'beta_url' => env('DEEPSEEK_BETA_URL', 'https://api.deepseek.com/beta'),
            'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),
            'timeout' => (int) env('DEEPSEEK_TIMEOUT', 30),
            'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 800),
            'use_strict_tools' => (bool) env('DEEPSEEK_USE_STRICT_TOOLS', true),
        ],

        'chatai' => [
            'label' => 'ChatAI',
            'enabled' => (bool) env('CHATAI_ENABLED', false),
            'api_key' => env('CHATAI_API_KEY'),
            'base_url' => env('CHATAI_BASE_URL', 'https://api.openai.com/v1'),
            'model' => env('CHATAI_MODEL', 'gpt-4o-mini'),
            'timeout' => (int) env('CHATAI_TIMEOUT', 30),
            'max_tokens' => (int) env('CHATAI_MAX_TOKENS', 800),
            'use_strict_tools' => (bool) env('CHATAI_USE_STRICT_TOOLS', true),
        ],

    ],

];
