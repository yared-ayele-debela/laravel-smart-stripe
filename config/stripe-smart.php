<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Stripe API Keys
    |--------------------------------------------------------------------------
    */
    'secret_key' => env('STRIPE_SECRET_KEY'),
    'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Currency
    |--------------------------------------------------------------------------
    */
    'currency' => env('STRIPE_CURRENCY', 'usd'),

    /*
    |--------------------------------------------------------------------------
    | Fraud Detection
    |--------------------------------------------------------------------------
    */
    'fraud_detection' => [
        'enabled' => env('STRIPE_FRAUD_DETECTION', true),
        'max_payments_per_ip_per_hour' => 10,
        'max_payments_per_user_per_hour' => 5,
        'suspicious_countries' => [], // Add country codes to block
        'rapid_payment_window_seconds' => 60,
        'max_rapid_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Retry (for subscriptions)
    |--------------------------------------------------------------------------
    */
    'retry' => [
        'enabled' => true,
        'schedule' => [
            1 => 60,      // 1st retry: 1 hour (minutes)
            2 => 1440,    // 2nd retry: 24 hours
            3 => 4320,    // 3rd retry: 3 days
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Smart Metadata
    |--------------------------------------------------------------------------
    */
    'metadata' => [
        'enabled' => true,
        'include' => ['user_id', 'ip', 'browser', 'device', 'country', 'app_name', 'laravel_version'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Logging
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'enabled' => true,
        'table' => 'stripe_payment_logs',
    ],

    /*
    |--------------------------------------------------------------------------
    | Test Mode Simulator
    |--------------------------------------------------------------------------
    */
    'simulator' => [
        'enabled' => env('STRIPE_SIMULATOR_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Route
    |--------------------------------------------------------------------------
    */
    'webhook_path' => 'stripe/webhook',

    /*
    |--------------------------------------------------------------------------
    | Billable Table (for stripe_id column)
    |--------------------------------------------------------------------------
    */
    'billable_table' => 'users',

    /*
    |--------------------------------------------------------------------------
    | Webhook Listeners (config-based)
    |--------------------------------------------------------------------------
    | Register listeners here or use StripeWebhook::listen() in AppServiceProvider
    */
    'webhook_listeners' => [],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'enabled' => true,
        'max_attempts' => 60,
        'decay_minutes' => 1,
    ],

];
