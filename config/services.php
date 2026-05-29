<?php

declare(strict_types=1);

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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_url' => env('GOOGLE_REDIRECT_URL', 'https://accounts.google.com/o/oauth2/v2/auth'),
        'token_url' => env('GOOGLE_TOKEN_URL', 'https://oauth2.googleapis.com/token'),
        'user_url' => env('GOOGLE_USER_URL', 'https://www.googleapis.com/oauth2/v3/userinfo'),
        'scopes' => ['openid', 'profile', 'email'],
    ],

    'facebook' => [
        'client_id' => env('FACEBOOK_CLIENT_ID'),
        'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
        'redirect_url' => env('FACEBOOK_REDIRECT_URL', 'https://www.facebook.com/v20.0/dialog/oauth'),
        'token_url' => env('FACEBOOK_TOKEN_URL', 'https://graph.facebook.com/v20.0/oauth/access_token'),
        'user_url' => env('FACEBOOK_USER_URL', 'https://graph.facebook.com/me?fields=id,name,email,picture'),
        'scopes' => ['email', 'public_profile'],
    ],

    'geocoding' => [
        'endpoint' => env('GEOCODING_ENDPOINT'),
        'reverse_endpoint' => env('GEOCODING_REVERSE_ENDPOINT', env('GEOCODING_ENDPOINT')),
        'key' => env('GEOCODING_KEY'),
        'timeout' => env('GEOCODING_TIMEOUT', 2),
        'limit' => env('GEOCODING_LIMIT', 5),
    ],

];
