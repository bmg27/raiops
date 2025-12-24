<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | RAI Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for communicating with RAI instances via webhooks.
    | The webhook secret is used to sign and verify webhook payloads.
    |
    */

    'rai' => [
        'webhook_secret' => env('SCHEDULE_WEBHOOK_SECRET'),
        'webhook_timeout' => env('RAI_WEBHOOK_TIMEOUT', 30),
        // Base URL for callbacks from RAI -> RAIOPS (fallback to APP_URL if not set)
        'callback_base_url' => env('RAIOPS_CALLBACK_URL', env('APP_URL')),
    ],

];
