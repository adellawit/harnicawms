<?php

return [

    'enabled' => (bool) env('AGENT_ENABLED', false),

    'widget_enabled' => (bool) env('AGENT_WIDGET_ENABLED', env('AGENT_ENABLED', false)),

    /*
    | Provider LLM untuk agent chat: deepseek | chatai
    | Detail API key & model ada di config/ai.php
    */
    'provider' => strtolower((string) env('AI_PROVIDER', 'deepseek')),

    'max_tool_rounds' => (int) env('AGENT_MAX_TOOL_ROUNDS', 5),

    'max_message_length' => (int) env('AGENT_MAX_MESSAGE_LENGTH', 2000),

    'conversation_retention_days' => (int) env('AGENT_CONVERSATION_RETENTION_DAYS', 90),

    'rate_limit_per_minute' => (int) env('AGENT_RATE_LIMIT_PER_MINUTE', 30),

    'permission_menu' => env('AGENT_PERMISSION_MENU', 'AI Assistant'),

    'timeout' => (int) env('AGENT_LLM_TIMEOUT', env('DEEPSEEK_TIMEOUT', 30)),

    'allowed_tools' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('AGENT_ALLOWED_TOOLS', 'search_docs,search_product,get_stock,search_customer,get_sales_summary,get_help,manage_sale,manage_record,guide_tour,open_page'))
    ))),

    /*
    | Identitas chatbot yang tampil di UI dan dipakai di system prompt.
    */
    'assistant_name' => env('AGENT_ASSISTANT_NAME', 'TITANIE'),

    'sales_number_prefix' => env('AGENT_SALES_NUMBER_PREFIX', 'AIT'),

    'draft_ttl_minutes' => (int) env('AGENT_DRAFT_TTL_MINUTES', 60),

    'stock_adjust_max_items' => (int) env('AGENT_STOCK_ADJUST_MAX_ITEMS', 40),

    'allowed_payment_codes' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('AGENT_ALLOWED_PAYMENT_CODES', 'CASH,TUNAI'))
    ))),

    /*
    | Basis pengetahuan FAQ chatbot.
    |
    | Isi jawaban diambil dari file markdown di folder ini lewat tool
    | search_docs — bukan ditulis di system prompt. Dengan begitu dokumentasi
    | repo tetap jadi satu-satunya sumber kebenaran.
    */
    'docs' => [
        'path' => env('AGENT_DOCS_PATH', base_path('docs')),

        // Batas panjang tiap kutipan yang dikirim ke LLM.
        'max_section_chars' => (int) env('AGENT_DOCS_MAX_SECTION_CHARS', 1600),

        // File lebih besar dari ini dilewati agar prompt tidak membengkak.
        'max_file_bytes' => (int) env('AGENT_DOCS_MAX_FILE_BYTES', 512000),

        /*
        | Subfolder yang tidak ikut jadi pengetahuan chatbot.
        |
        | docs/superpowers/ berisi handoff dan spec desain historis per fitur.
        | Isinya keadaan saat fitur dikerjakan, bukan keadaan sistem sekarang,
        | sehingga kalau diikutkan chatbot bisa menjawab dengan rencana lama.
        */
        'exclude' => ['superpowers'],

        // Dokumen yang dipakai untuk pertanyaan umum tanpa kata kunci spesifik.
        'overview' => ['PRODUCT_KNOWLEDGE.md', 'AI_CONTEXT.md', 'PRD.md', 'SCOPE.md', 'ARCHITECTURE.md'],
    ],

];
