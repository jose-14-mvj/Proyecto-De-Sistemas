<?php

namespace App\Http\Controllers;

use App\Http\Requests\OlvideClaveRequest;
use App\Http\Requests\RestablecerClaveRequest;
use App\Services\CorreoService;
use App\Services\RecuperacionClaveService;
use Illuminate\Http\JsonResponse;

/**
 * "¿Olvidaste tu contraseña?" (rutas públicas, con límite de intentos).
 *   POST /api/clave/olvide      {email}                                  → envía un código de 6 dígitos
 *   POST /api/clave/restablecer {email, codigo, password, password_confirmation}
 */
class RecuperacionClaveController extends Controller
{
    public function __construct(private RecuperacionClaveService $recuperacion, private CorreoService $correo)
    {
    }

    public function olvide(OlvideClaveRequest $request): JsonResponse
    {
        // Falta de configuración: afecta a TODOS los correos por igual, así que no revela nada
        if (! $this->correo->disponible()) {
            return response()->json(['error' => 'La recuperación por correo no está disponible por ahora. Intenta más tarde.'], 503);
        }

        $inicio = microtime(true);
        $this->recuperacion->solicitar($request->validated('email'), $request->ip());

        // Misma demora exista o no la cuenta (enviar el correo tarda): no se puede adivinar quién está registrado
        $minimo = (int) config('services.correo.respuesta_min_ms', 1500);
        $falta = $minimo - (int) ((microtime(true) - $inicio) * 1000);
        if ($falta > 0) {
            usleep($falta * 1000);
        }

        return response()->json([
            'ok'      => true,
            'mensaje' => 'Si el correo está registrado, te enviamos un código de 6 números. Revisa también la carpeta de spam.',
            'minutos' => RecuperacionClaveService::MINUTOS_VALIDEZ,
        ]);
    }

    public function restablecer(RestablecerClaveRequest $request): JsonResponse
    {
        $d = $request->validated();

        if (! $this->recuperacion->restablecer($d['email'], $d['codigo'], $d['password'])) {
            return response()->json([
                'message' => 'El código no es correcto o ya venció.',
                'errors'  => ['codigo' => ['El código no es correcto o ya venció. Si fallaste 5 veces, pide uno nuevo.']],
            ], 422);
        }

        return response()->json(['ok' => true, 'mensaje' => 'Listo. Ya puedes iniciar sesión con tu nueva contraseña.']);
    }
}
