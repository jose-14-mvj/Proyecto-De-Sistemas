<?php

namespace App\Http\Controllers;

use Illuminate\Database\QueryException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Ejecuta una operación que escribe en la BD (procedimiento almacenado o trigger) y devuelve su respuesta.
     *
     * - SQLSTATE 45000 = regla de negocio del SP o de un trigger → 422 con su mensaje (pensado para la persona).
     * - Cualquier otro error se relanza → 500 genérico; el detalle queda solo en storage/logs.
     *
     * Antes este try/catch estaba copiado en cinco controladores.
     */
    protected function conReglasDeBd(\Closure $accion, string $siFalla = 'No se pudo guardar'): JsonResponse
    {
        try {
            return $accion();
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '45000') {
                return response()->json(['error' => $e->errorInfo[2] ?? $siFalla], 422);
            }
            throw $e;
        }
    }

    /** 404 para un recurso que no existe o que es de otra persona (no revela cuál de los dos casos es). */
    protected function noEncontrado(string $mensaje = 'Registro no encontrado'): JsonResponse
    {
        return response()->json(['error' => $mensaje], 404);
    }
}
