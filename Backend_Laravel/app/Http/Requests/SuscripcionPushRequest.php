<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suscripción Web Push enviada por el navegador (PushSubscription.toJSON()).
 * Solo se aceptan servicios de push reales: el servidor hará pedidos a esa dirección,
 * así que no se permite cualquier URL (evita que se use para atacar otros servidores).
 */
class SuscripcionPushRequest extends FormRequest
{
    public const HOSTS = [
        'fcm.googleapis.com', 'android.googleapis.com',          // Chrome, Edge (Android), Brave, Opera
        'push.services.mozilla.com',                             // Firefox
        'notify.windows.com',                                    // Edge en Windows
        'push.apple.com',                                        // Safari / iPhone (app instalada)
    ];

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $soloEndpoint = $this->isMethod('delete');
        return [
            'endpoint'     => ['required', 'string', 'max:1000', 'starts_with:https://', function ($a, $v, $fail) {
                $host = strtolower((string) parse_url((string) $v, PHP_URL_HOST));
                foreach (self::HOSTS as $h) {
                    if ($host === $h || str_ends_with($host, '.'.$h)) {
                        return;
                    }
                }
                $fail('Servicio de notificaciones no reconocido.');
            }],
            'keys.p256dh'  => $soloEndpoint ? ['nullable'] : ['required', 'string', 'regex:/^[A-Za-z0-9_-]{86,88}$/'],
            'keys.auth'    => $soloEndpoint ? ['nullable'] : ['required', 'string', 'regex:/^[A-Za-z0-9_-]{21,24}$/'],
        ];
    }
}
