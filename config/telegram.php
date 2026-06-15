<?php

return [

    'enabled' => (bool) env('TELEGRAM_ENABLED', false),

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    'api_base_url' => env('TELEGRAM_API_BASE_URL', 'https://api.telegram.org'),

    'ai_enabled' => (bool) env('TELEGRAM_AI_ENABLED', true),

    'session_ttl_minutes' => (int) env('TELEGRAM_SESSION_TTL', 30),

    /*
    | Masa berlaku kode /link (menit). Set 0 = tidak expired (valid sampai dipakai).
    */
    'link_code_ttl_minutes' => (int) env('TELEGRAM_LINK_CODE_TTL', 60),

    'menu_item_limit' => (int) env('TELEGRAM_MENU_ITEM_LIMIT', 100),

    'sales_number_prefix' => env('TELEGRAM_SALES_NUMBER_PREFIX', 'TGM'),

    'default_customer_group_code' => env('TELEGRAM_DEFAULT_CUSTOMER_GROUP', 'UMUM'),

    'default_tax_rate' => (float) env('TELEGRAM_DEFAULT_TAX_RATE', 0),

    'default_tax_enabled' => (bool) env('TELEGRAM_DEFAULT_TAX_ENABLED', false),

    /*
    | Metode pembayaran yang didukung via Telegram POS (MVP: cash).
    */
    'allowed_payment_codes' => array_values(array_filter(array_map(
        'strtoupper',
        array_map('trim', explode(',', env('TELEGRAM_ALLOWED_PAYMENT_CODES', 'CASH')))
    ))),

    /*
    | Base telegram_user_id untuk AccountSeeder (dev).
    */
    'seed_base_telegram_id' => (int) env('TELEGRAM_SEED_BASE_ID', 900000001),

    /*
    | Mode mock: tidak kirim ke Telegram API, hanya log (untuk test local).
    */
    'mock' => (bool) env('TELEGRAM_MOCK', false),

];
