<?php

return [
    'name' => env('SACCO_NAME', 'Staff Sacco Society Ltd'),

    // Multi-SACCO: one installation, one database per SACCO chosen by domain (see sacco:tenant-create)
    'multi_tenant' => env('MULTI_TENANT', false),
    'tenant' => null,

    // Public HTTPS address Safaricom can reach (live domain or ngrok). Falls back to APP_URL.
    'public_url' => env('PUBLIC_URL'),

    'sms' => [
        'enabled'    => env('SMS_ENABLED', false),
        'url'        => env('ADVANTA_URL', 'https://quicksms.advantasms.com/api/services/sendsms/'),
        'api_key'    => env('ADVANTA_API_KEY'),
        'partner_id' => env('ADVANTA_PARTNER_ID'),
        'shortcode'  => env('ADVANTA_SHORTCODE'),
    ],

    'mpesa' => [
        // Shared secret appended to the C2B confirmation URL: /api/mpesa/confirmation?token=XXXX
        'callback_token'  => env('MPESA_CALLBACK_TOKEN'),
        'paybill'         => env('MPESA_PAYBILL'),
        // Daraja STK Push (Lipa na M-Pesa Online)
        'env'             => env('MPESA_ENV', 'sandbox'), // sandbox | production
        'consumer_key'    => env('MPESA_CONSUMER_KEY'),
        'consumer_secret' => env('MPESA_CONSUMER_SECRET'),
        'passkey'         => env('MPESA_PASSKEY'),
        'stk_shortcode'   => env('MPESA_STK_SHORTCODE', env('MPESA_PAYBILL')),
    ],

    'whatsapp' => [
        'token'    => env('WHATSAPP_TOKEN'),
        'phone_id' => env('WHATSAPP_PHONE_ID'),
    ],

    'backup' => [
        'mysqldump' => env('MYSQLDUMP_PATH', 'mysqldump'), // XAMPP: C:\xampp\mysql\bin\mysqldump.exe
        'keep_days' => env('BACKUP_KEEP_DAYS', 30),
    ],
];
