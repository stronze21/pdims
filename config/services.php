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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'webex' => [
        'middleware_url' => env('WEBEX_MIDDLEWARE_URL', 'http://localhost:3100'),
        'middleware_api_key' => env('WEBEX_MIDDLEWARE_API_KEY'),
    ],

    'jitsi' => [
        'server_url' => env('JITSI_SERVER_URL', 'https://meet.jit.si'),
        'jwt_app_id' => env('JITSI_JWT_APP_ID'),
        'jwt_secret' => env('JITSI_JWT_SECRET'),
    ],

    'teleconsult' => [
        'default_platform' => env('TELECONSULT_DEFAULT_PLATFORM', 'jitsi'),
    ],

    'ntfy' => [
        'url' => env('NTFY_URL', 'http://ntfy.mmmhmc.local:2586'),
        'subscriber_url' => env('NTFY_SUBSCRIBER_URL'),
        'user' => env('NTFY_USER'),
        'password' => env('NTFY_PASSWORD'),
        'subscriber_user' => env('NTFY_SUBSCRIBER_USER'),
        'subscriber_password' => env('NTFY_SUBSCRIBER_PASSWORD'),
    ],

];
