<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditarRegistroRequest;
use App\Http\Requests\EpisodioRequest;
use App\Http\Requests\ProdromicoRequest;
use App\Services\RegistroService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistroController extends Controller
{
    public function __construct(private RegistroService $registros)
    {
    }

    /** GET /api/registros?tipo=todos|prodromico|episodio&dias=30 */
    public function index(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'tipo'   => ['nullable', 'in:todos,prodromico,episodio'],
            'dias'   => ['nullable', 'integer', 'between:0,3650'],
            'limite' => ['nullable', 'integer', 'between:1,1000'],
        ]);

        return response()->json([
            'ok'        => true,
            'registros' => $this->registros->listar(
                $request->user()->id_usuario,
                $datos['tipo'] ?? 'todos',
                (int) ($datos['dias'] ?? 0),
                (int) ($datos['limite'] ?? 500),
            ),
        ]);
    }

    /** POST /api/registros/prodromico */
    public function crearProdromico(ProdromicoRequest $request): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        return $this->crear($uid, 'prodromico', fn () => $this->registros->registrarProdromico(
            $uid, $request->validated(), $request->inicioCarbon()));
    }

    /** POST /api/registros/episodio */
    public function crearEpisodio(EpisodioRequest $request): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        return $this->crear($uid, 'episodio', fn () => $this->registros->registrarEpisodio(
            $uid, $request->validated(), $request->inicioCarbon()));
    }

    /** GET /api/registros/{tipo}/{id} */
    public function mostrar(Request $request, string $tipo, int $id): JsonResponse
    {
        $reg = $this->registros->obtener($request->user()->id_usuario, $tipo, $id);
        return $reg ? response()->json(['ok' => true, 'registro' => $reg]) : $this->noEncontrado();
    }

    /** PUT /api/registros/{tipo}/{id} — intensidad, notas y medicamento */
    public function editar(EditarRegistroRequest $request, string $tipo, int $id): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        if (! $this->registros->obtener($uid, $tipo, $id)) {
            return $this->noEncontrado();
        }

        try {
            $request->esCompleta()
                ? $this->registros->editarCompleto($uid, $tipo, $id, $request->validated(),
                    $request->filled('inicio') ? $request->inicioCarbon() : null)
                : $this->registros->editar($uid, $tipo, $id, $request->validated());
        } catch (QueryException $e) {
            return $this->errorDeSp($e);
        }

        return response()->json(['ok' => true, 'registro' => $this->registros->obtener($uid, $tipo, $id)]);
    }

    /** DELETE /api/registros/{tipo}/{id} */
    public function eliminar(Request $request, string $tipo, int $id): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        if (! $this->registros->obtener($uid, $tipo, $id)) {
            return $this->noEncontrado();
        }
        $this->registros->eliminar($uid, $tipo, $id);
        return response()->json(['ok' => true]);
    }

    private function crear(int $uid, string $tipo, \Closure $accion): JsonResponse
    {
        try {
            $id = $accion();
        } catch (QueryException $e) {
            return $this->errorDeSp($e);
        }
        return response()->json(['ok' => true, 'registro' => $this->registros->obtener($uid, $tipo, $id)], 201);
    }

    /** SQLSTATE 45000 = regla de negocio del SP o de un trigger (mensaje pensado para el usuario). */
    private function errorDeSp(QueryException $e): JsonResponse
    {
        if (($e->errorInfo[0] ?? '') === '45000') {
            return response()->json(['error' => $e->errorInfo[2] ?? 'No se pudo guardar'], 422);
        }
        throw $e;   // otro error: 500 genérico, detalle solo en storage/logs
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json(['error' => 'Registro no encontrado'], 404);
    }
}
