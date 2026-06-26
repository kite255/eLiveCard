<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file stores credentials for third-party services such as Postmark,
    | Resend, AWS SES, Slack, SMS gateways, and WhatsApp Cloud API.
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
    | This block is used for sending SMS.
    |
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'api_url' => env(
            'SMS_API_URL',
            'https://message.elive.co.tz/api/v1/vendor/message/send'
        ),

        'api_key' => env('SMS_API_KEY'),
        'api_secret' => env('SMS_API_SECRET'),

        'sender_id' => env('SMS_SENDER_ID', 'eLiveCard'),

        'timeout' => (int) env('SMS_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | eLive SMS Provider API
    |--------------------------------------------------------------------------
    |
    | This block is used for SMS balance checking and delivery reports.
    | The provider expects api_key and api_secret request headers.
    |
    | Delivery report:
    | GET /delivery/{shootId}
    |
    | Balance:
    | GET /balance
    |
    */

    'elive_sms' => [
        'base_url' => env(
            'ELIVE_SMS_BASE_URL',
            'https://message.elive.co.tz/api/v1/vendor/message'
        ),

        'api_key' => env(
            'ELIVE_SMS_API_KEY',
            env('SMS_API_KEY')
        ),

        'api_secret' => env(
            'ELIVE_SMS_API_SECRET',
            env('SMS_API_SECRET')
        ),

        'timeout' => (int) env(
            'ELIVE_SMS_TIMEOUT',
            env('SMS_TIMEOUT', 30)
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | Supported drivers:
    |
    | log       - Records payloads in Laravel logs without calling Meta.
    | cloud_api - Sends real WhatsApp messages using Meta Cloud API.
    |
    | Never commit the access token, app secret, or webhook verify token.
    |
    */

    'whatsapp' => [

        /*
        |--------------------------------------------------------------------------
        | Driver and status
        |--------------------------------------------------------------------------
        */

        'enabled' => filter_var(
            env('WHATSAPP_ENABLED', false),
            FILTER_VALIDATE_BOOL
        ),

        'driver' => env('WHATSAPP_DRIVER', 'log'),

        'provider' => env('WHATSAPP_PROVIDER', 'cloud_api'),

        /*
        |--------------------------------------------------------------------------
        | Meta Cloud API credentials
        |--------------------------------------------------------------------------
        */

        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),

        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),

        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),

        'app_id' => env('WHATSAPP_APP_ID'),

        'app_secret' => env('WHATSAPP_APP_SECRET'),

        /*
        |--------------------------------------------------------------------------
        | API settings
        |--------------------------------------------------------------------------
        |
        | Keep the API version configurable because Meta retires older Graph API
        | versions over time.
        |
        */

        'api_version' => env('WHATSAPP_API_VERSION', 'v25.0'),

        'base_url' => env(
            'WHATSAPP_API_URL',
            'https://graph.facebook.com'
        ),

        'timeout' => (int) env('WHATSAPP_TIMEOUT', 30),

        'connect_timeout' => (int) env(
            'WHATSAPP_CONNECT_TIMEOUT',
            10
        ),

        /*
        |--------------------------------------------------------------------------
        | Webhook configuration
        |--------------------------------------------------------------------------
        */

        'webhook_verify_token' => env(
            'WHATSAPP_WEBHOOK_VERIFY_TOKEN'
        ),

        'verify_webhook_signature' => filter_var(
            env('WHATSAPP_VERIFY_WEBHOOK_SIGNATURE', true),
            FILTER_VALIDATE_BOOL
        ),

        /*
        |--------------------------------------------------------------------------
        | Template language
        |--------------------------------------------------------------------------
        */

        'template_language' => env(
            'WHATSAPP_TEMPLATE_LANGUAGE',
            'sw'
        ),

        /*
        |--------------------------------------------------------------------------
        | WhatsApp templates
        |--------------------------------------------------------------------------
        */

        'templates' => [
            'invitation' => env(
                'WHATSAPP_INVITATION_TEMPLATE',
                'elive_invitation'
            ),

            'rsvp' => env(
                'WHATSAPP_RSVP_TEMPLATE',
                'elive_rsvp'
            ),

            'reminder' => env(
                'WHATSAPP_REMINDER_TEMPLATE',
                'elive_reminder'
            ),

            'event_day' => env(
                'WHATSAPP_EVENT_DAY_TEMPLATE',
                'elive_event_day'
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Default template header
        |--------------------------------------------------------------------------
        |
        | Supported values:
        | none, image, document, video
        |
        */

        'invitation_header_type' => env(
            'WHATSAPP_INVITATION_HEADER_TYPE',
            'none'
        ),

        /*
        |--------------------------------------------------------------------------
        | Message behaviour
        |--------------------------------------------------------------------------
        */

        'mark_generated_card_as_sent' => filter_var(
            env('WHATSAPP_MARK_CARD_AS_SENT', true),
            FILTER_VALIDATE_BOOL
        ),

        'store_provider_response' => filter_var(
            env('WHATSAPP_STORE_PROVIDER_RESPONSE', true),
            FILTER_VALIDATE_BOOL
        ),

        'capture_incoming_messages' => filter_var(
            env('WHATSAPP_CAPTURE_INCOMING_MESSAGES', true),
            FILTER_VALIDATE_BOOL
        ),
    ],

];