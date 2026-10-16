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
        // Aprendizaje continuo: reentrenar solo con los datos de los usuarios
        'auto_reentrenar'   => filter_var(env('ML_AUTO_REENTRENAR', true), FILTER_VALIDATE_BOOLEAN),
        'reentrenar_horas'  => (int) env('ML_REENTRENAR_HORAS', 24),        // como máximo 1 vez cada N horas
        'reentrenar_minimo' => (int) env('ML_REENTRENAR_MINIMO', 20),       // registros nuevos necesarios
        'simulados'         => (int) env('ML_SIMULADOS', 200),              // base de estudios que se mezcla
    ],

    // Correos (recuperar contraseña) por HTTPS: Render gratis bloquea SMTP.
    // Si hay Apps Script se usa ese (sale desde Gmail de verdad); si no, Brevo.
    // Notificaciones Web Push (claves VAPID: php artisan migrasense:claves-push)
    'push' => [
        'publica' => (string) env('VAPID_PUBLIC_KEY', ''),
        'privada' => (string) env('VAPID_PRIVATE_KEY', ''),
        'sujeto'  => (string) env('VAPID_SUBJECT', 'mailto:migrasense@example.com'),
    ],

    // Despertador externo (cron-job.org) que llama a POST /api/tareas/recordatorios
    'cron' => [
        'clave'          => (string) env('CRON_CLAVE', ''),
        'ventana_horas'  => (int) env('RECORDATORIO_VENTANA_HORAS', 3),   // si el aviso se atrasa, hasta cuánto después aún se envía
    ],

    'correo' => [
        'appsscript_url'     => env('CORREO_APPSSCRIPT_URL', ''),
        'appsscript_secreto' => env('CORREO_APPSSCRIPT_SECRETO', ''),
        'brevo_key'         => env('BREVO_API_KEY', ''),
        'remitente'         => env('CORREO_REMITENTE', ''),
        'remitente_nombre'  => env('CORREO_REMITENTE_NOMBRE', 'MigraSense'),
        'frontend_url'      => rtrim((string) env('FRONTEND_URL', 'https://miguelhuanca676-bot.github.io/migrasense'), '/'),
        'respuesta_min_ms'  => (int) env('CORREO_RESPUESTA_MIN_MS', 1500),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

];
