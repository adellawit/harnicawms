<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Xendit API
    |--------------------------------------------------------------------------
    */

    'secret_key' => env('XENDIT_SECRET_KEY'),

    'webhook_token' => env('XENDIT_WEBHOOK_TOKEN'),

    'api_base_url' => env('XENDIT_API_BASE_URL', 'https://api.xendit.co'),

    'enabled' => (bool) env('XENDIT_ENABLED', false),

    /*
    | true  = POS/Shop/Telegram memakai Payment Gateway (Xendit) + Cash.
    | false = hanya metode pembayaran manual (tanpa PG).
    */
    'use_payment_gateway' => (bool) env('XENDIT_USE_PAYMENT_GATEWAY', false),

    /*
    | Kode method_payment internal yang diproses via Xendit.
    */
    'method_codes' => array_filter(array_map(
        'trim',
        explode(',', env('XENDIT_METHOD_CODES', 'QRIS,TRANSFER,EWALLET'))
    )),

    'invoice_duration' => (int) env('XENDIT_INVOICE_DURATION', 900),

    /*
    | Sinkronkan daftar channel POS dari akun Xendit (invoice probe + cache).
    | true = hanya tampilkan channel yang aktif di Dashboard Xendit Anda.
    */
    'sync_channels_from_api' => (bool) env('XENDIT_SYNC_CHANNELS', true),

    'channels_cache_ttl' => (int) env('XENDIT_CHANNELS_CACHE_TTL', 3600),

    'channel_probe_amount' => (int) env('XENDIT_CHANNEL_PROBE_AMOUNT', 10000),

    /*
    | Opsional: batasi lagi channel dari .env (subset dari yang aktif di Xendit).
    | Kosongkan = tampilkan semua yang aktif di akun Xendit.
    | Contoh: XENDIT_ALLOWED_PAYMENT_METHODS=BCA,QRIS,OVO
    */
    'allowed_payment_methods' => array_values(array_filter(array_map(
        'strtoupper',
        array_map('trim', explode(',', env('XENDIT_ALLOWED_PAYMENT_METHODS', '')))
    ))),

    /*
    | Alias kode internal → kode Xendit Invoice API.
    */
    'channel_aliases' => [
        'SHOPEE' => 'SHOPEEPAY',
        'SHOPEEPAY' => 'SHOPEEPAY',
        'LINK_AJA' => 'LINKAJA',
        'BSS' => 'SAHABAT_SAMPOERNA',
    ],

    /*
    | Katalog channel: label tampilan + grup internal (TRANSFER|QRIS|EWALLET).
    | Yang tampil di POS = irisan katalog ini dengan channel aktif di akun Xendit (API).
    */
    'channel_catalog' => [
        'BCA' => ['label' => 'BCA', 'group' => 'TRANSFER'],
        'MANDIRI' => ['label' => 'Mandiri', 'group' => 'TRANSFER'],
        'BNI' => ['label' => 'BNI', 'group' => 'TRANSFER'],
        'BRI' => ['label' => 'BRI', 'group' => 'TRANSFER'],
        'PERMATA' => ['label' => 'Permata', 'group' => 'TRANSFER'],
        'CIMB' => ['label' => 'CIMB Niaga', 'group' => 'TRANSFER'],
        'BSI' => ['label' => 'BSI', 'group' => 'TRANSFER'],
        'BJB' => ['label' => 'BJB', 'group' => 'TRANSFER'],
        'BSS' => ['label' => 'BSS', 'group' => 'TRANSFER'],
        'SAHABAT_SAMPOERNA' => ['label' => 'BSS', 'group' => 'TRANSFER'],
        'QRIS' => ['label' => 'QRIS', 'group' => 'QRIS'],
        'OVO' => ['label' => 'OVO', 'group' => 'EWALLET'],
        'DANA' => ['label' => 'DANA', 'group' => 'EWALLET'],
        'SHOPEEPAY' => ['label' => 'ShopeePay', 'group' => 'EWALLET'],
        'LINKAJA' => ['label' => 'LinkAja', 'group' => 'EWALLET'],
        'ASTRAPAY' => ['label' => 'AstraPay', 'group' => 'EWALLET'],
    ],

    /*
    | Judul grup di modal POS (method_code = master_data.method_payments).
    */
    'payment_channel_groups' => [
        ['title' => 'Bank Transfer', 'method_code' => 'TRANSFER'],
        ['title' => 'QRIS', 'method_code' => 'QRIS'],
        ['title' => 'E-Wallet', 'method_code' => 'EWALLET'],
    ],

    /*
    | Fallback map method_code → semua channel (jika channel tidak dipilih).
    */
    'payment_methods_map' => [
        'QRIS' => ['QRIS'],
        'TRANSFER' => ['BCA', 'BNI', 'BRI', 'MANDIRI', 'PERMATA'],
        'EWALLET' => ['OVO', 'DANA', 'SHOPEEPAY', 'LINKAJA'],
    ],

    /*
    | Ikon grup (Tabler Icons class) di judul kategori.
    */
    'group_icons' => [
        'TRANSFER' => 'ti-building-bank',
        'QRIS' => 'ti-qrcode',
        'EWALLET' => 'ti-wallet',
        'DEBIT' => 'ti-credit-card',
        'CREDIT' => 'ti-credit-card',
    ],

    /*
    | Logo channel (path relatif dari public/). Bisa override per channel di config groups.
    */
    'channel_icons' => [
        'BCA' => 'assets/img/payments/bca.svg',
        'MANDIRI' => 'assets/img/payments/mandiri.svg',
        'BNI' => 'assets/img/payments/bni.svg',
        'BRI' => 'assets/img/payments/bri.svg',
        'PERMATA' => 'assets/img/payments/permata.svg',
        'CIMB' => 'assets/img/payments/cimb.svg',
        'BSI' => 'assets/img/payments/bsi.svg',
        'BJB' => 'assets/img/payments/bjb.svg',
        'BSS' => 'assets/img/payments/bss.svg',
        'SAHABAT_SAMPOERNA' => 'assets/img/payments/bss.svg',
        'QRIS' => 'assets/img/payments/qris.svg',
        'OVO' => 'assets/img/payments/ovo.svg',
        'DANA' => 'assets/img/payments/dana.svg',
        'SHOPEEPAY' => 'assets/img/payments/shopeepay.svg',
        'LINKAJA' => 'assets/img/payments/linkaja.svg',
        'ASTRAPAY' => 'assets/img/payments/astrapay.svg',
        'CREDIT_CARD' => 'assets/img/payments/credit_card.svg',
    ],

];
