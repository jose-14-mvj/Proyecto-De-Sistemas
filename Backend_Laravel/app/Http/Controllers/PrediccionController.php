<?php

namespace App\Http\Controllers;

use App\Services\PrediccionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class PrediccionController extends Controller
{
    public function __construct(private PrediccionService $prediccion)
    {
    }

    /**
     * GET /api/prediccion — probabilidad de migraña en las próximas 48 h para el usuario del token.
     * 503 si el servicio de ML no está configurado o no responde (el frontend puede reintentar).
     */
    public function mostrar(Request $request): JsonResponse
    {
        try {
            $r = $this->prediccion->predecir($request->user()->id_usuario);
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }

        unset($r['ok']);
        return response()->json(['ok' => true, 'prediccion' => $r]);
    }
}
