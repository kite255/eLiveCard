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
    | SMS Gateway
    |--------------------------------------------------------------------------
    |
    | For now use SMS_DRIVER=log so you can test safely without sending real SMS.
    | Later change SMS_DRIVER=http and add your provider URL/API key.
    |
    */

'sms' => [
    'driver' => env('SMS_DRIVER', 'log'),
    'api_url' => env('SMS_API_URL'),
    'api_key' => env('SMS_API_KEY'),
    'api_secret' => env('SMS_API_SECRET'),
    'sender_id' => env('SMS_SENDER_ID', 'eLiveCard'),
    'timeout' => env('SMS_TIMEOUT', 30),
],
];