<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * پنل مدیریت روی مبدأ خودش (زیر‌دامنه) اجرا می‌شود، پس مبدأهای مجاز دیگر با
     * دو خانه پر نمی‌شوند. FRONTEND_URLS یک فهرست جداشده با کاما است و در کنار
     * دو متغیر قدیمی کار می‌کند تا تنظیم فعلی سرور نشکند.
     *
     * فیلتر پایانی لازم است: متغیر تنظیم‌نشده null می‌دهد و null داخل این فهرست
     * برای مقایسه‌ی مبدأ بی‌معناست.
     */
    'allowed_origins' => array_values(array_filter(
        array_map(
            // trim روی null در PHP 8.1 به بالا اخطار منسوخ‌شدگی می‌دهد
            static fn ($origin) => trim((string) $origin),
            array_merge(
                [env('FRONTEND_URL1'), env('FRONTEND_URL2')],
                explode(',', (string) env('FRONTEND_URLS', '')),
            ),
        ),
        static fn (string $origin) => $origin !== '',
    )),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
