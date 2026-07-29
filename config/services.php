<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third-Party Services
    |--------------------------------------------------------------------------
    |
    | Credentials and configuration for external services used by eLive Card.
    | Sensitive credentials must be stored in the environment file and never
    | committed to Git.
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
            'bot_user_oauth_token' => env(
                'SLACK_BOT_USER_OAUTH_TOKEN'
            ),

            'channel' => env(
                'SLACK_BOT_USER_DEFAULT_CHANNEL'
            ),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Gateway
    |--------------------------------------------------------------------------
    */

    'sms' => [
        'driver' => env('SMS_DRIVER', 'log'),

        'provider' => env(
            'SMS_PROVIDER',
            'eLive SMS'
        ),

        'api_url' => env(
            'SMS_API_URL',
            'https://message.elive.co.tz/api/v1/vendor/message/send'
        ),

        'api_key' => env('SMS_API_KEY'),

        'api_secret' => env('SMS_API_SECRET'),

        'sender_id' => env(
            'SMS_SENDER_ID',
            'eLive Card'
        ),

        'timeout' => (int) env(
            'SMS_TIMEOUT',
            30
        ),

        'connect_timeout' => (int) env(
            'SMS_CONNECT_TIMEOUT',
            10
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | eLive SMS Provider
    |--------------------------------------------------------------------------
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

        'delivery_report_path' => env(
            'ELIVE_SMS_DELIVERY_REPORT_PATH',
            '/deliver/{shootId}'
        ),

        'balance_path' => env(
            'ELIVE_SMS_BALANCE_PATH',
            '/balance'
        ),

        'timeout' => (int) env(
            'ELIVE_SMS_TIMEOUT',
            env('SMS_TIMEOUT', 30)
        ),

        'connect_timeout' => (int) env(
            'ELIVE_SMS_CONNECT_TIMEOUT',
            env('SMS_CONNECT_TIMEOUT', 10)
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | SMS Balance
    |--------------------------------------------------------------------------
    */

    'sms_balance' => [
        'url' => env(
            'SMS_BALANCE_URL',
            rtrim(
                env(
                    'ELIVE_SMS_BASE_URL',
                    'https://message.elive.co.tz/api/v1/vendor/message'
                ),
                '/'
            ).'/balance'
        ),

        'method' => strtolower(
            env('SMS_BALANCE_METHOD', 'get')
        ),

        'api_key' => env(
            'SMS_BALANCE_API_KEY',
            env(
                'ELIVE_SMS_API_KEY',
                env('SMS_API_KEY')
            )
        ),

        'api_secret' => env(
            'SMS_BALANCE_API_SECRET',
            env(
                'ELIVE_SMS_API_SECRET',
                env('SMS_API_SECRET')
            )
        ),

        'timeout' => (int) env(
            'SMS_BALANCE_TIMEOUT',
            env(
                'ELIVE_SMS_TIMEOUT',
                env('SMS_TIMEOUT', 30)
            )
        ),

        'connect_timeout' => (int) env(
            'SMS_BALANCE_CONNECT_TIMEOUT',
            env(
                'ELIVE_SMS_CONNECT_TIMEOUT',
                env('SMS_CONNECT_TIMEOUT', 10)
            )
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Cloud API
    |--------------------------------------------------------------------------
    |
    | WhatsApp starts disabled after the reset.
    |
    | Supported drivers:
    |
    | log            - Writes message payloads to the Laravel log.
    | meta_cloud_api - Sends real messages through Meta WhatsApp Cloud API.
    |
    | Enable meta_cloud_api only after the Meta app, WABA, phone number,
    | permanent access token and webhook have been configured successfully.
    |
    */

    'whatsapp' => [

        /*
        |--------------------------------------------------------------------------
        | Integration state
        |--------------------------------------------------------------------------
        */

        'enabled' => filter_var(
            env('WHATSAPP_ENABLED', false),
            FILTER_VALIDATE_BOOL
        ),

        'driver' => env(
            'WHATSAPP_DRIVER',
            'log'
        ),

        'provider' => env(
            'WHATSAPP_PROVIDER',
            'meta_cloud_api'
        ),

        /*
        |--------------------------------------------------------------------------
        | Meta account identifiers
        |--------------------------------------------------------------------------
        */

        'app_id' => env(
            'WHATSAPP_APP_ID'
        ),

        'business_id' => env(
            'WHATSAPP_BUSINESS_ID'
        ),

        'business_account_id' => env(
            'WHATSAPP_BUSINESS_ACCOUNT_ID',
            env('WHATSAPP_BUSINESS_ID')
        ),

        'phone_number_id' => env(
            'WHATSAPP_PHONE_NUMBER_ID'
        ),

        'display_phone_number' => env(
            'WHATSAPP_DISPLAY_PHONE_NUMBER'
        ),

        /*
        |--------------------------------------------------------------------------
        | Meta API credentials
        |--------------------------------------------------------------------------
        */

        'access_token' => env(
            'WHATSAPP_ACCESS_TOKEN'
        ),

        'app_secret' => env(
            'WHATSAPP_APP_SECRET'
        ),

        /*
        |--------------------------------------------------------------------------
        | Graph API settings
        |--------------------------------------------------------------------------
        */

        'api_version' => env(
            'WHATSAPP_API_VERSION',
            'v25.0'
        ),

        'base_url' => rtrim(
            env(
                'WHATSAPP_API_URL',
                'https://graph.facebook.com'
            ),
            '/'
        ),

        'timeout' => (int) env(
            'WHATSAPP_TIMEOUT',
            30
        ),

        'connect_timeout' => (int) env(
            'WHATSAPP_CONNECT_TIMEOUT',
            10
        ),

        'retry_times' => (int) env(
            'WHATSAPP_RETRY_TIMES',
            2
        ),

        'retry_delay' => (int) env(
            'WHATSAPP_RETRY_DELAY',
            500
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
            env(
                'WHATSAPP_VERIFY_WEBHOOK_SIGNATURE',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        'webhook_signature_header' => env(
            'WHATSAPP_WEBHOOK_SIGNATURE_HEADER',
            'X-Hub-Signature-256'
        ),

        'capture_incoming_messages' => filter_var(
            env(
                'WHATSAPP_CAPTURE_INCOMING_MESSAGES',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        'capture_message_statuses' => filter_var(
            env(
                'WHATSAPP_CAPTURE_MESSAGE_STATUSES',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        /*
        |--------------------------------------------------------------------------
        | WhatsApp template
        |--------------------------------------------------------------------------
        |
        | eLive Card uses one approved WhatsApp template for invitations,
        | RSVP quick replies and location requests.
        |
        | Meta template name: event_invitation_en
        | Template language: English
        |
        */

        'template_language' => env(
            'WHATSAPP_TEMPLATE_LANGUAGE',
            'en'
        ),

        'templates' => [
            'invitation' => env(
                'WHATSAPP_TEMPLATE_INVITATION',
                'event_invitation_en'
            ),
        ],

        /*
        |--------------------------------------------------------------------------
        | Invitation media
        |--------------------------------------------------------------------------
        */

        'invitation_header_type' => env(
            'WHATSAPP_INVITATION_HEADER_TYPE',
            'image'
        ),

        'default_invitation_image_url' => env(
            'WHATSAPP_DEFAULT_INVITATION_IMAGE_URL'
        ),

        /*
        |--------------------------------------------------------------------------
        | Message behaviour
        |--------------------------------------------------------------------------
        */

        'mark_generated_card_as_sent' => filter_var(
            env(
                'WHATSAPP_MARK_CARD_AS_SENT',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        'store_provider_response' => filter_var(
            env(
                'WHATSAPP_STORE_PROVIDER_RESPONSE',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        'store_request_payload' => filter_var(
            env(
                'WHATSAPP_STORE_REQUEST_PAYLOAD',
                false
            ),
            FILTER_VALIDATE_BOOL
        ),

        'prevent_duplicate_sending' => filter_var(
            env(
                'WHATSAPP_PREVENT_DUPLICATE_SENDING',
                true
            ),
            FILTER_VALIDATE_BOOL
        ),

        'duplicate_window_minutes' => (int) env(
            'WHATSAPP_DUPLICATE_WINDOW_MINUTES',
            10
        ),

        /*
        |--------------------------------------------------------------------------
        | Queue configuration
        |--------------------------------------------------------------------------
        */

        'queue' => env(
            'WHATSAPP_QUEUE',
            'whatsapp'
        ),

        'queue_connection' => env(
            'WHATSAPP_QUEUE_CONNECTION',
            env('QUEUE_CONNECTION', 'database')
        ),

        /*
        |--------------------------------------------------------------------------
        | Logging
        |--------------------------------------------------------------------------
        */

        'log_channel' => env(
            'WHATSAPP_LOG_CHANNEL',
            env('LOG_CHANNEL', 'stack')
        ),

        'log_sensitive_data' => filter_var(
            env(
                'WHATSAPP_LOG_SENSITIVE_DATA',
                false
            ),
            FILTER_VALIDATE_BOOL
        ),
    ],

];