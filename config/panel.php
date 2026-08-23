<?php

return [
    // نامی که روی کانفیگ‌های تولیدشده می‌نشیند
    'brand' => env('PANEL_BRAND', 'MyVPN'),

    // دامنه‌ای که لینک اشتراک با آن ساخته می‌شود
    'sub_domain' => rtrim(env('PANEL_SUB_DOMAIN', env('APP_URL', 'http://localhost')), '/'),

    // دامنهٔ ساختگی برای email tag کاربران در Xray (کلید شمارش مصرف)
    'email_domain' => env('PANEL_EMAIL_DOMAIN', 'panel'),

    'currency' => env('PANEL_CURRENCY', 'IRT'),

    'expiry_warning_days' => (int) env('PANEL_EXPIRY_WARNING_DAYS', 3),

    'xray' => [
        'bin' => env('PANEL_XRAY_BIN', '/usr/local/bin/xray'),
        // سرویس xray در همین compose؛ فقط از داخل شبکهٔ داکر در دسترس است.
        'api' => env('PANEL_XRAY_API', 'xray:10085'),
    ],

    // اطلاعات پرداخت کارت‌به‌کارت که در صفحهٔ پرداخت نمایش داده می‌شود
    'payment' => [
        'card_number' => env('PANEL_CARD_NUMBER', ''),
        'card_holder' => env('PANEL_CARD_HOLDER', ''),
        'bank' => env('PANEL_BANK', ''),
    ],

    'support' => [
        'telegram' => env('PANEL_SUPPORT_TELEGRAM', ''),
    ],
];
