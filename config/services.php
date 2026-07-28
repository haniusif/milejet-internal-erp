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

    // ID/licence OCR. driver: 'none' (disabled) | 'tesseract' (free, local) | 'google_vision'.
    //   tesseract     → no key; uses the local `tesseract` binary (ara+eng).
    //   google_vision → set OCR_API_KEY to a Cloud Vision API key.
    'ocr' => [
        'driver'         => env('OCR_DRIVER', 'none'),
        'key'            => env('OCR_API_KEY'),
        'endpoint'       => env('OCR_ENDPOINT'),
        'tesseract_bin'  => env('OCR_TESSERACT_BIN', 'tesseract'),
        'tesseract_langs' => env('OCR_TESSERACT_LANGS', 'ara+eng'),
    ],

    // Firebase Cloud Messaging (mobile push). Sending is a no-op until a service
    // account is configured. FCM_CREDENTIALS = absolute path to the Firebase
    // service-account JSON; project_id is read from it (or FCM_PROJECT_ID).
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),        // path to service-account .json
        'project_id'  => env('FCM_PROJECT_ID'),         // optional override
    ],

];
