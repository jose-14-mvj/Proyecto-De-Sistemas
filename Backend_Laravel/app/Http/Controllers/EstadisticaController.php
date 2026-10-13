<?php

namespace App\Http\Controllers;

use App\Services\EstadisticaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EstadisticaController extends Controller
{
    public function __construct(private EstadisticaService $estadisticas)
    {
    }

    /** GET /api/estadisticas?semanas=12&top=5 — todo lo que necesita el dashboard en una sola llamada */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'semanas' => ['nullable', 'integer', 'between:4,52'],
            'top'     => ['nullable', 'integer', 'between:1,16'],
        ]);
        $uid = $request->user()->id_usuario;
        $top = (int) ($d['top'] ?? 5);

        return response()->json([
            'ok'              => true,
            'resumen'         => $this->estadisticas->resumen($uid),
            'semanas'         => $this->estadisticas->semanas($uid, (int) ($d['semanas'] ?? 12)),
            'desencadenantes' => $this->estadisticas->desencadenantes($uid, $top),
            'sintomas'        => $this->estadisticas->sintomas($uid, $top),
            'patrones'        => $this->estadisticas->patrones($uid),
        ]);
    }
}
