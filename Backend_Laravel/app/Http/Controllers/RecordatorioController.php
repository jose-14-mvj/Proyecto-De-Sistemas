<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordatoriosRequest;
use App\Services\RecordatorioService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RecordatorioController extends Controller
{
    public function __construct(private RecordatorioService $recordatorios)
    {
    }

    /** GET /api/recordatorios */
    public function mostrar(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'recordatorios' => $this->recordatorios->obtener($request->user()->id_usuario)]);
    }

    /** PUT /api/recordatorios — actualización parcial de daily / weekly / alerta */
    public function actualizar(RecordatoriosRequest $request): JsonResponse
    {
        try {
            $r = $this->recordatorios->guardar($request->user()->id_usuario, $request->validated());
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '45000') {
                return response()->json(['error' => $e->errorInfo[2] ?? 'No se pudo guardar'], 422);
            }
            throw $e;
        }

        return response()->json(['ok' => true, 'recordatorios' => $r]);
    }
}
