<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'founding_price' => env('STRIPE_FOUNDING_PRICE'),
    ],

    'microsoft' => [
        'tenant_id' => env('MICROSOFT_TENANT_ID'),
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'studio_calendar' => env('MICROSOFT_STUDIO_CALENDAR'),
        'soundtunnel_calendar' => env('MICROSOFT_SOUNDTUNNEL_CALENDAR'),
        'events_calendar' => env('MICROSOFT_EVENTS_CALENDAR'),
        'mail' => [
            'tenant_id' => env('MICROSOFT_MAIL_TENANT_ID'),
            'client_id' => env('MICROSOFT_MAIL_CLIENT_ID'),
            'client_secret' => env('MICROSOFT_MAIL_CLIENT_SECRET'),
            'mailbox' => env('MICROSOFT_MAIL_MAILBOX', 'booking@wavemakerconcepts.com'),
        ],
    ],

    'nextiva' => [
        'username' => env('NEXTIVA_USERNAME'),
        'password' => env('NEXTIVA_PASSWORD'),
        'from' => env('NEXTIVA_FROM'),
        'campaign_id' => env('NEXTIVA_CAMPAIGN_ID'),
        'base_url' => env('NEXTIVA_BASE_URL', 'https://api.nextiva.com'),
    ],

    'mwcn' => [
        'dj_form_url' => env('MWCN_DJ_FORM_URL'),
        'soundtunnel_form_url' => env('MWCN_SOUNDTUNNEL_FORM_URL'),
        'timezone' => env('MWCN_TIMEZONE', 'America/Phoenix'),
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

];
