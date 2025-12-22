<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Impersonation Secret
    |--------------------------------------------------------------------------
    |
    | This secret is used to sign JWT tokens for impersonation. It MUST be
    | shared with all RAI instances that RAINBO will impersonate into.
    | Generate a secure 64-character random string.
    |
    */
    'impersonation_secret' => env('RAINBO_IMPERSONATION_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Impersonation Token Expiry
    |--------------------------------------------------------------------------
    |
    | How long (in minutes) an impersonation token is valid. Keep this short
    | for security - the token is only used during the redirect.
    |
    */
    'impersonation_token_expiry_minutes' => env('RAINBO_IMPERSONATION_TOKEN_EXPIRY', 5),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | Secret for validating webhooks received from RAI instances.
    | Used to push audit events back to RAINBO.
    |
    */
    'webhook_secret' => env('RAINBO_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Ghost User Email Pattern
    |--------------------------------------------------------------------------
    |
    | Pattern used for ghost admin users in RAI. The {id} placeholder
    | will be replaced with the RAINBO admin's ID.
    |
    */
    'ghost_user_email_pattern' => 'rainbo-admin-{id}@system.internal',

    /*
    |--------------------------------------------------------------------------
    | Ghost User Cleanup Days
    |--------------------------------------------------------------------------
    |
    | How many days of inactivity before ghost users are cleaned up in RAI.
    |
    */
    'ghost_user_cleanup_days' => env('RAINBO_GHOST_USER_CLEANUP_DAYS', 90),
];

