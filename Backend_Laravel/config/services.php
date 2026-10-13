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
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    // Servicio de predicción de MigraSense (FastAPI en Render)
    'ml' => [
        'url'     => rtrim((string) env('ML_URL', ''), '/'),
        'key'     => env('ML_API_KEY'),
        'timeout' => (int) env('ML_TIMEOUT', 20),   // por intento (el ML responde en <2 s cuando está despierto)
        'reintentos' => (int) env('ML_REINTENTOS', 5),     // intentos si el ML está despertando (502/503/504)
        'pausa_ms'   => (int) env('ML_PAUSA_MS', 10000),   // espera entre intentos
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
