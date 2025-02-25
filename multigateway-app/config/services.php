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

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'gateway1' => [
        'url' => env('GATEWAY1_URL', 'http://gateway1:3001'),
        'email' => env('GATEWAY1_EMAIL', 'dev@betalent.tech'),
        'token' => env('GATEWAY1_TOKEN', 'FEC9BB078BF338F464F96B48089EB498'),
    ],

    'gateway2' => [
        'url' => env('GATEWAY2_URL', 'http://gateway2:3002'),
        'auth_token' => env('GATEWAY2_AUTH_TOKEN', 'tk_f2198cc671b5289fa856'),
        'auth_secret' => env('GATEWAY2_AUTH_SECRET', '3d15e8ed6131446ea7e3456728b1211f'),
    ],

];
