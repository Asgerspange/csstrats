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

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT'),
    ],

    'bunny' => [
        'access_key' => env('BUNNY_ACCESS_KEY'),
        'storage_zone' => env('BUNNY_STORAGE_ZONE'),
        'storage_password' => env('BUNNY_STORAGE_PASSWORD'),
        'cdn_url' => env('BUNNY_CDN_URL'),
        'storage_url' => env('BUNNY_STORAGE_URL', 'https://storage.bunnycdn.com'),
        'region' => env('BUNNY_REGION', 'de'), // Default to Germany region
    ],

    'faceit' => [
        'client_id' => env('FACEIT_CLIENT_ID'),
        'client_secret' => env('FACEIT_CLIENT_SECRET'),
        'redirect' => env('FACEIT_REDIRECT_URL'),
        'pkce' => true,
        'data_api_key' => env('FACEIT_API_KEY'),
    ],
    'paddle' => [
        'client_side_token' => env('PADDLE_CLIENT_SIDE_TOKEN'),
        'sandbox' => env('PADDLE_SANDBOX', false),
    ],
    'demoviewer' => [
        // Client key allow-listed for csstrats.dk in the demoviewer API.
        'server_key' => env('DEMOVIEWER_SERVER_KEY'),
        'client_key' => env('DEMOVIEWER_CLIENT_KEY'),
        'sample_demo_id' => env('DEMOVIEWER_SAMPLE_DEMO_ID', 1),
        'api_base' => env('DEMOVIEWER_API_BASE', 'https://api.csstrats.dk'),
        'viewer_src' => env('DEMOVIEWER_VIEWER_SRC', 'https://demoviewer.b-cdn.net/viewer.js'),
    ],
    'odin' => [
        'base_url' => env('ODIN_BASE_URL', 'https://api.ordbogen.ai/v1'),
        'api_key' => env('ODIN_API_KEY'),
        'model' => env('ODIN_MODEL', 'odin-medium'),
    ],
    'subtick_record' => [
        'api_url' => env('SUBTICK_RECORD_API_URL', 'https://subtick.gg/api'),
        'key' => env('SUBTICK_RECORD_KEY'),
        'callback_key' => env('RECORD_CALLBACK_KEY'),
    ],
    'youtube' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'refresh_token' => env('YOUTUBE_REFRESH_TOKEN'),
        'redirect_uri' => env('YOUTUBE_REDIRECT_URI', 'https://csstrats.dk/admin/youtube/callback'),
    ],
];
