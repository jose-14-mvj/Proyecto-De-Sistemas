<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilRequest;
use App\Services\PerfilService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PerfilController extends Controller
{
    public function __construct(private PerfilService $perfiles)
    {
    }

    /** GET /api/perfil */
    public function mostrar(Request $request): JsonResponse
    {
        return response()->json([
            'ok'     => true,
            'perfil' => $this->perfiles->cargar($request->user()->id_usuario),
        ]);
    }

    /** PUT /api/perfil — guarda o rehace el cuestionario del usuario autenticado */
    public function guardar(PerfilRequest $request): JsonResponse
    {
        $id = $request->user()->id_usuario;   // siempre el dueño del token: nunca un id enviado por el cliente

        try {
            $this->perfiles->guardar($id, $request->validated());
        } catch (QueryException $e) {
            // SQLSTATE 45000 = validación del procedimiento almacenado (mensaje pensado para el usuario)
            if (($e->errorInfo[0] ?? '') === '45000') {
                return response()->json(['error' => $e->errorInfo[2] ?? 'No se pudo guardar el cuestionario'], 422);
            }
            throw $e;   // otro error: 500 genérico (el detalle queda solo en storage/logs)
        }

        return response()->json(['ok' => true, 'perfil' => $this->perfiles->cargar($id)]);
    }
}
