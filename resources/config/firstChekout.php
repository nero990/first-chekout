<?php

return [
    'test_secret' => env('FIRSTCHEKOUT_MERCHANT_TEST_SECRET'),

    'live_secret' => env('FIRSTCHEKOUT_MERCHANT_LIVE_SECRET'),

    'code' => env('FIRSTCHEKOUT_MERCHANT_CODE'),

    'test_callback_url' => env('FIRSTCHEKOUT_TEST_CALLBACK_URL', 'http://www.example.com/callback'),

    'live_callback_url' => env('FIRSTCHEKOUT_LIVE_CALLBACK_URL', 'http://www.example.com/callback'),

    'test_mode' => env('FIRST_CHEKOUT_TEST_MODE', true),
];