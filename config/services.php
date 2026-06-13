<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores credentials for third party services such as Postmark,
    | Resend, AWS SES, Slack, SMS gateway, and WhatsApp provider/API.
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
    | Use SMS_DRIVER=log while testing locally.
    | Use SMS_DRIVER=http when connecting to the real SMS provider.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),
        'api_url' => env('SMS_API_URL'),
        'api_key' => env('SMS_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),
        'sender_id' => env('SMS_SENDER_ID', 'eLiveCard'),
        'timeout' => (int) env('SMS_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Gateway
    |--------------------------------------------------------------------------
    |
    | Used for sending invitation cards, private invitee links, RSVP buttons,
    | reminders, and event-day messages through WhatsApp.
    |
    | For MVP testing, keep WHATSAPP_DRIVER=log.
    | Later use WHATSAPP_DRIVER=cloud_api or your trusted provider.
    |
    */

    'whatsapp' => [
        'driver' => env('WHATSAPP_DRIVER', 'log'),
        'provider' => env('WHATSAPP_PROVIDER', 'cloud_api'),

        // Meta WhatsApp Cloud API credentials
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),

        // Optional provider URL if you later use another WhatsApp gateway
        'api_url' => env('WHATSAPP_API_URL'),

        // Default template names
        'invitation_template' => env('WHATSAPP_INVITATION_TEMPLATE', 'elive_invitation'),
        'rsvp_template' => env('WHATSAPP_RSVP_TEMPLATE', 'elive_rsvp'),
        'reminder_template' => env('WHATSAPP_REMINDER_TEMPLATE', 'elive_reminder'),

        'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),
    ],

];