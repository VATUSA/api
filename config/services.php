<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Stripe, Mailgun, SparkPost and others. This file provides a sane
    | default location for this type of information, allowing packages
    | to have a conventional place to find your various credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
    ],

    'ses' => [
        'key' => env('SES_KEY'),
        'secret' => env('SES_SECRET'),
        'region' => 'us-east-1',
    ],

    'sparkpost' => [
        'secret' => env('SPARKPOST_SECRET'),
    ],

    'stripe' => [
        'model' => App\User::class,
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
    ],

    'moodle' => [
        'url'   => env('MOODLE_URL', 'https://academy.vatusa.net'),
        'token' => env('MOODLE_TOKEN'),
        'token_sso' => env('MOODLE_TOKEN_SSO')
    ],

    'moodle_test' => [
        'url'   => env('MOODLE_TEST_URL', ''),
        'token' => env('MOODLE_TEST_TOKEN'),
        'token_sso' => env('MOODLE_TEST_TOKEN_SSO')
    ]

];
