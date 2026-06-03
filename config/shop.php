<?php

return [

    'tax_rate' => (float) env('SHOP_TAX_RATE', 11),

    'tax_enabled' => (bool) env('SHOP_TAX_ENABLED', true),

    'sales_number_prefix' => env('SHOP_SALES_PREFIX', 'WEB'),

    /*
    | Label channel di bawah judul katalog, contoh: Bandung (take out)
    */
    'channel_label' => env('SHOP_CHANNEL_LABEL', 'take out'),

    'default_company_name' => env('SHOP_DEFAULT_COMPANY_NAME', 'WWW'),

];
