<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp Notifications Master Switch
    |--------------------------------------------------------------------------
    |
    | Enables or disables sending WhatsApp notifications application-wide.
    |
    */
    'enabled' => env('WHATSAPP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Should Queue WhatsApp Messages
    |--------------------------------------------------------------------------
    |
    | When true, messages will be pushed to the database/redis queue via job.
    | When false, messages are processed immediately during the request.
    |
    */
    'queue' => env('WHATSAPP_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Default Gateway Driver
    |--------------------------------------------------------------------------
    |
    | Supported drivers: "log", "fonnte", "waha", "generic"
    |
    | - "log": Writes message payload to Laravel logs. 100% free, safe for development/testing.
    | - "fonnte": Indonesian commercial/trial gateway (https://fonnte.com).
    | - "waha": Self-hosted WhatsApp HTTP API (Docker/Node.js/Baileys).
    | - "generic": Custom webhook/HTTP endpoint for any custom WhatsApp bridge.
    |
    */
    'default' => env('WHATSAPP_DRIVER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Driver Configurations
    |--------------------------------------------------------------------------
    */
    'drivers' => [
        'log' => [
            'channel' => env('WHATSAPP_LOG_CHANNEL', 'single'),
        ],

        'fonnte' => [
            'endpoint' => env('FONNTE_ENDPOINT', 'https://api.fonnte.com/send'),
            'token' => env('FONNTE_TOKEN', ''),
        ],

        'waha' => [
            'endpoint' => env('WAHA_ENDPOINT', 'http://localhost:3000/api/sendText'),
            'session' => env('WAHA_SESSION', 'default'),
            'api_key' => env('WAHA_API_KEY', ''),
        ],

        'generic' => [
            'endpoint' => env('WHATSAPP_GENERIC_ENDPOINT', ''),
            'method' => env('WHATSAPP_GENERIC_METHOD', 'POST'),
            'api_key' => env('WHATSAPP_GENERIC_API_KEY', ''),
            'phone_key' => env('WHATSAPP_GENERIC_PHONE_KEY', 'phone'),
            'message_key' => env('WHATSAPP_GENERIC_MESSAGE_KEY', 'message'),
        ],
    ],
];
