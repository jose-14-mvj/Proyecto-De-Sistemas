<?php

namespace App\Http\Controllers;

use App\Http\Requests\EditarRegistroRequest;
use App\Http\Requests\EpisodioRequest;
use App\Http\Requests\ProdromicoRequest;
use App\Http\Requests\TerminarEpisodioRequest;
use App\Services\RegistroService;
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
            $uid, $request->validated(), $request->inicioCarbon(), $request->filled('fin') ? $request->finCarbon() : null));
    }

    /** GET /api/registros/en-curso — la migraña marcada "Aún en curso" (o null) */
    public function enCurso(Request $request): JsonResponse
    {
        return response()->json(['ok' => true, 'registro' => $this->registros->enCurso($request->user()->id_usuario)]);
    }

    /** POST /api/registros/episodio/{id}/terminar — "La migraña terminó" (fin = ahora, o la hora enviada) */
    public function terminar(TerminarEpisodioRequest $request, int $id): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        if (! $this->registros->obtener($uid, 'episodio', $id)) {
            return $this->noEncontrado();
        }
        if (! $this->registros->terminar($uid, $id, $request->finCarbon())) {
            return response()->json(['error' => 'Esta migraña ya estaba marcada como terminada.'], 422);
        }
        return response()->json(['ok' => true, 'registro' => $this->registros->obtener($uid, 'episodio', $id)]);
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

        return $this->conReglasDeBd(function () use ($request, $uid, $tipo, $id) {
            $request->esCompleta()
                ? $this->registros->editarCompleto($uid, $tipo, $id, $request->validated(),
                    $request->filled('inicio') ? $request->inicioCarbon() : null,
                    $tipo === 'episodio' && $request->filled('fin') ? $request->finCarbon() : null)
                : $this->registros->editar($uid, $tipo, $id, $request->validated());

            return response()->json(['ok' => true, 'registro' => $this->registros->obtener($uid, $tipo, $id)]);
        });
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

    /** Plantilla común de los POST: guarda con $accion (devuelve el id) y responde 201 con el registro creado */
    private function crear(int $uid, string $tipo, \Closure $accion): JsonResponse
    {
        return $this->conReglasDeBd(fn () => response()->json(
            ['ok' => true, 'registro' => $this->registros->obtener($uid, $tipo, $accion())], 201));
    }
}
