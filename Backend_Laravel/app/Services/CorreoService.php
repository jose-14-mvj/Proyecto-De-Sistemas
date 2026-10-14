<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Envío de correos con la API HTTPS de Brevo (antes Sendinblue).
 *
 * Por qué HTTPS y no SMTP: Render (plan gratis) bloquea los puertos SMTP 25/465/587,
 * así que Gmail/SMTP no funcionan ahí. La API de Brevo va por el puerto 443.
 *
 * Variables de entorno (.env / Render):
 *   BREVO_API_KEY           clave de API de Brevo (xkeysib-...)
 *   CORREO_REMITENTE        correo verificado en Brevo como remitente
 *   CORREO_REMITENTE_NOMBRE nombre que ve la persona (por defecto "MigraSense")
 *
 * Sin BREVO_API_KEY y con APP_ENV=local, el correo NO se envía: se escribe en
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
        return (string) config('services.correo.brevo_key') !== ''
            && (string) config('services.correo.remitente') !== '';
    }

    /**
     * Envía un correo. Lanza RuntimeException si Brevo lo rechaza o no responde.
     */
    public function enviar(string $para, string $nombre, string $asunto, string $html, string $texto): void
    {
        if (! $this->configurado()) {
            if (app()->environment('local')) {
                Log::info("[CORREO SIMULADO · solo local] Para: {$para}\nAsunto: {$asunto}\n\n{$texto}");
                return;
            }
            throw new RuntimeException('El envío de correos no está configurado (BREVO_API_KEY / CORREO_REMITENTE).');
        }

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
            // Solo el código y el mensaje de Brevo (nunca el contenido del correo: lleva el código)
            $motivo = mb_substr((string) ($r->json('code') ?? '').' '.($r->json('message') ?? ''), 0, 200);
            throw new RuntimeException("Brevo rechazó el correo (HTTP {$r->status()}): {$motivo}");
        }
    }
}
