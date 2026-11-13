<?php

namespace App\Http\Controllers;

use App\Http\Requests\SuscripcionPushRequest;
use App\Services\PushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Avisos al dispositivo (Web Push): clave pública, suscribirse, quitar y probar. */
class PushController extends Controller
{
    public function __construct(private PushService $push)
    {
    }

    /** GET /api/push/clave */
    public function clave(Request $request): JsonResponse
    {
        return response()->json([
            'ok' => true, 'configurado' => $this->push->configurado(), 'clave' => $this->push->clavePublica(),
            'dispositivos' => $this->push->cantidad($request->user()->id_usuario),
        ]);
    }

    /** POST /api/push/suscripcion  { endpoint, keys:{p256dh, auth} } */
    public function suscribir(SuscripcionPushRequest $request): JsonResponse
    {
        if (! $this->push->configurado()) {
            return response()->json(['ok' => false, 'mensaje' => 'Los avisos al dispositivo todavía no están configurados en el servidor.'], 503);
        }
        $d = $request->validated();
        $this->push->suscribir($request->user()->id_usuario, $d['endpoint'], $d['keys']['p256dh'], $d['keys']['auth'],
            (string) $request->userAgent());
        return response()->json(['ok' => true, 'dispositivos' => $this->push->cantidad($request->user()->id_usuario)], 201);
    }

    /** DELETE /api/push/suscripcion  { endpoint } */
    public function quitar(SuscripcionPushRequest $request): JsonResponse
    {
        $this->push->quitar($request->user()->id_usuario, $request->validated()['endpoint']);
        return response()->json(['ok' => true]);
    }

    /**
     * POST /api/push/renovar  { anterior, endpoint, keys:{p256dh, auth} }   (sin sesión)
     * Lo llama el Service Worker cuando el navegador cambia la suscripción por su cuenta.
     * El Service Worker no tiene el token de la sesión; la prueba de que es el mismo
     * dispositivo es conocer la dirección anterior, que es secreta y solo él la tiene.
     */
    public function renovar(SuscripcionPushRequest $request): JsonResponse
    {
        $d = $request->validated();
        $ok = $this->push->renovar((string) $request->input('anterior'), $d['endpoint'], $d['keys']['p256dh'], $d['keys']['auth']);
        return response()->json(['ok' => $ok], $ok ? 200 : 404);
    }

    /** POST /api/push/prueba: manda un aviso de prueba a todos los dispositivos de la persona */
    public function prueba(Request $request): JsonResponse
    {
        $n = $this->push->enviarAUsuario($request->user()->id_usuario, [
            'title' => 'Avisos activados',
            'body'  => 'Así te llegarán los recordatorios de MigraSense, aunque la app esté cerrada.',
            'url'   => 'perfil.html#recordatorios',
            'tag'   => 'prueba',
        ]);
        return response()->json(['ok' => $n > 0, 'enviados' => $n,
            'mensaje' => $n > 0 ? "Aviso enviado a {$n} dispositivo(s)." : 'No hay dispositivos con avisos activados en esta cuenta.']);
    }
}
