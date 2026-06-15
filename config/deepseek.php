<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DeepSeek API (Telegram POS parser & legacy)
    |--------------------------------------------------------------------------
    |
    | Untuk WMS Agent Chat widget, gunakan config/ai.php + AI_PROVIDER.
    |
    */

    'enabled' => (bool) env('DEEPSEEK_ENABLED', true),

    'api_key' => env('DEEPSEEK_API_KEY'),

    'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com'),

    'beta_url' => env('DEEPSEEK_BETA_URL', 'https://api.deepseek.com/beta'),

    'model' => env('DEEPSEEK_MODEL', 'deepseek-chat'),

    'timeout' => (int) env('DEEPSEEK_TIMEOUT', 15),

    'max_tokens' => (int) env('DEEPSEEK_MAX_TOKENS', 800),

    'use_strict_tools' => (bool) env('DEEPSEEK_USE_STRICT_TOOLS', true),

];
