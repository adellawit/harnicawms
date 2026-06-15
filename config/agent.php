<?php

return [

    'enabled' => (bool) env('AGENT_ENABLED', false),

    'max_tool_rounds' => (int) env('AGENT_MAX_TOOL_ROUNDS', 5),

    'max_message_length' => (int) env('AGENT_MAX_MESSAGE_LENGTH', 2000),

    'conversation_retention_days' => (int) env('AGENT_CONVERSATION_RETENTION_DAYS', 90),

    'rate_limit_per_minute' => (int) env('AGENT_RATE_LIMIT_PER_MINUTE', 30),

    'permission_menu' => env('AGENT_PERMISSION_MENU', 'AI Assistant'),

    'timeout' => (int) env('AGENT_DEEPSEEK_TIMEOUT', 30),

    'allowed_tools' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('AGENT_ALLOWED_TOOLS', 'search_product,get_stock,search_customer,get_sales_summary,get_help'))
    ))),

];
