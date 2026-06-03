<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Customer app — temporary password (development only)
    |--------------------------------------------------------------------------
    |
    | Semua customer dengan has_app_access & email aktif memakai password ini
    | sampai modul reset password / hash DB diaktifkan.
    |
    */
    'temp_password' => env('CUSTOMER_TEMP_PASSWORD', 'pass123'),

];
