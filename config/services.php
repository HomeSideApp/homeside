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
    | Crypto market prices
    |--------------------------------------------------------------------------
    |
    | CoinGecko resolves the spot prices used to value the crypto accounts. The free tier needs no
    | key, so the key is optional and only sent when configured.
    |
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        'mobile_client_ids' => array_filter(explode(',', (string) env('GOOGLE_MOBILE_CLIENT_IDS', ''))),
    ],

    'coingecko' => [
        'base_url' => env('COINGECKO_BASE_URL', 'https://api.coingecko.com'),
        'key' => env('COINGECKO_API_KEY'),
        'quote_currency' => env('ECONOMY_BASE_CURRENCY', 'EUR'),
    ],

];
