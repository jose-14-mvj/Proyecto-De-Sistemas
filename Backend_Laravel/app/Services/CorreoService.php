<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envío de correos por HTTPS (Render gratis bloquea los puertos SMTP 25/465/587).
 *
 * Dos formas (se usa la primera que esté configurada):
 *
 * 1) Google Apps Script (RECOMENDADO si el remitente es un @gmail.com)
 *    El correo sale DE VERDAD desde la cuenta de Gmail del equipo, así que Gmail lo acepta.
 *    (Con Brevo + remitente @gmail.com, Gmail rechaza los correos: falla DMARC.)
 *      CORREO_APPSSCRIPT_URL      URL de la "aplicación web" del script (https://script.google.com/macros/s/.../exec)
 *      CORREO_APPSSCRIPT_SECRETO  la misma clave secreta guardada en las propiedades del script
 *    Límite de Google: ~100 destinatarios por día (cuenta gratis).
 *
 * 2) API de Brevo (para cuando tengan un dominio propio autenticado en Brevo)
 *      BREVO_API_KEY, CORREO_REMITENTE
 *
 * CORREO_REMITENTE_NOMBRE: nombre que ve la persona (por defecto "MigraSense").
 *
 * Sin ninguna configurada y con APP_ENV=local, el correo NO se envía: se escribe en
 * storage/logs/laravel.log para poder probar en la PC (nunca en producción).
 */
class CorreoService
{
    public const URL_BREVO = 'https://api.brevo.com/v3/smtp/email';

    /** ¿Se pueden enviar correos (o al menos simularlos en local)? */
    public function disponible(): bool
    {
        return $this->configurado() || app()->environment('local');
    }

    public function configurado(): bool
    {
        return $this->usaAppsScript() || $this->usaBrevo();
    }

    private function usaAppsScript(): bool
    {
        return (string) config('services.correo.appsscript_url') !== ''
            && (string) config('services.correo.appsscript_secreto') !== '';
    }

    private function usaBrevo(): bool
    {
        return (string) config('services.correo.brevo_key') !== ''
            && (string) config('services.correo.remitente') !== '';
    }

    /**
     * Envía un correo. Lanza RuntimeException si el proveedor lo rechaza o no responde.
     */
    public function enviar(string $para, string $nombre, string $asunto, string $html, string $texto): void
    {
        if ($this->usaAppsScript()) {
            $this->enviarAppsScript($para, $asunto, $html, $texto);
            return;
        }
        if ($this->usaBrevo()) {
            $this->enviarBrevo($para, $nombre, $asunto, $html, $texto);
            return;
        }
        if (app()->environment('local')) {
            Log::info("[CORREO SIMULADO · solo local] Para: {$para}\nAsunto: {$asunto}\n\n{$texto}");
            return;
        }
        throw new RuntimeException('El envío de correos no está configurado (CORREO_APPSSCRIPT_URL o BREVO_API_KEY).');
    }

    private function enviarAppsScript(string $para, string $asunto, string $html, string $texto): void
    {
        $url = (string) config('services.correo.appsscript_url');
        if (! str_starts_with($url, 'https://script.google.com/')) {
            throw new RuntimeException('CORREO_APPSSCRIPT_URL debe empezar con https://script.google.com/');
        }

        try {
            // Google responde con una redirección (302) a script.googleusercontent.com: se sigue sola
            $r = Http::acceptJson()
                ->timeout(25)
                ->post($url, [
                    'secreto' => (string) config('services.correo.appsscript_secreto'),
                    'para'    => $para,
                    'nombre'  => (string) config('services.correo.remitente_nombre', 'MigraSense'),
                    'asunto'  => $asunto,
                    'html'    => $html,
                    'texto'   => $texto,
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Apps Script no respondió: '.class_basename($e));
        }

        if (! $r->successful() || $r->json('ok') !== true) {
            // Nunca el contenido del correo (lleva el código)
            $motivo = mb_substr((string) ($r->json('error') ?? 'respuesta inesperada'), 0, 200);
            throw new RuntimeException("Apps Script no envió el correo (HTTP {$r->status()}): {$motivo}");
        }
    }

    private function enviarBrevo(string $para, string $nombre, string $asunto, string $html, string $texto): void
    {
        try {
            $r = Http::withHeaders(['api-key' => (string) config('services.correo.brevo_key')])
                ->acceptJson()
                ->timeout(15)
                ->post(self::URL_BREVO, [
                    'sender'      => [
                        'email' => (string) config('services.correo.remitente'),
                        'name'  => (string) config('services.correo.remitente_nombre', 'MigraSense'),
                    ],
                    'to'          => [['email' => $para, 'name' => $nombre !== '' ? $nombre : $para]],
                    'subject'     => $asunto,
                    'htmlContent' => $html,
                    'textContent' => $texto,
                ]);
        } catch (\Throwable $e) {
            throw new RuntimeException('Brevo no respondió: '.class_basename($e));
        }

        if (! $r->successful()) {
            $motivo = mb_substr((string) ($r->json('code') ?? '').' '.($r->json('message') ?? ''), 0, 200);
            throw new RuntimeException("Brevo rechazó el correo (HTTP {$r->status()}): {$motivo}");
        }
    }
}
