<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilRequest;
use App\Services\PerfilService;
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

        return $this->conReglasDeBd(function () use ($request, $id) {
            $this->perfiles->guardar($id, $request->validated());

            return response()->json(['ok' => true, 'perfil' => $this->perfiles->cargar($id)]);
        }, 'No se pudo guardar el cuestionario');
    }
}
