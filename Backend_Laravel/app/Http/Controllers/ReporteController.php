<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteRequest;
use App\Services\PerfilService;
use App\Services\ReporteService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Reportes para llevar a consulta. El PDF se arma en el navegador con estos datos:
 *   MSReportePDF.generar({ user: usuario, onboarding: perfil, records: registros,
 *                          dias: reporte.dias || null, etiqueta: reporte.etiqueta, ... })
 */
class ReporteController extends Controller
{
    public function __construct(private ReporteService $reportes, private PerfilService $perfiles)
    {
    }

    /** GET /api/reportes?limite=50 — historial de reportes generados */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate(['limite' => ['nullable', 'integer', 'between:1,200']]);

        return response()->json([
            'ok'       => true,
            'reportes' => $this->reportes->listar($request->user()->id_usuario, (int) ($d['limite'] ?? 50)),
        ]);
    }

    /** POST /api/reportes {dias, formato?} — registra el reporte y devuelve sus datos */
    public function crear(ReporteRequest $request): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        $d = $request->validated();

        try {
            $id = $this->reportes->generar($uid, (int) $d['dias'], $d['formato'] ?? 'pdf');
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '45000') {
                return response()->json(['error' => $e->errorInfo[2] ?? 'No se pudo generar el reporte'], 422);
            }
            throw $e;   // otro error: 500 genérico, detalle solo en storage/logs
        }

        return response()->json($this->datos($request, $id), 201);
    }

    /** GET /api/reportes/{id} — vuelve a entregar los datos de un reporte ya generado */
    public function mostrar(Request $request, int $id): JsonResponse
    {
        if (! $this->reportes->obtener($request->user()->id_usuario, $id)) {
            return $this->noEncontrado();
        }
        return response()->json($this->datos($request, $id));
    }

    /** DELETE /api/reportes/{id} */
    public function eliminar(Request $request, int $id): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        if (! $this->reportes->obtener($uid, $id)) {
            return $this->noEncontrado();
        }
        $this->reportes->eliminar($uid, $id);
        return response()->json(['ok' => true]);
    }

    private function datos(Request $request, int $id): array
    {
        $usuario = $request->user();
        $uid = $usuario->id_usuario;

        return [
            'ok'        => true,
            'reporte'   => $this->reportes->obtener($uid, $id),
            'usuario'   => $usuario->paraFrontend(),
            'perfil'    => $this->perfiles->cargar($uid),
            'registros' => $this->reportes->registrosDe($uid, $id),
        ];
    }

    private function noEncontrado(): JsonResponse
    {
        return response()->json(['error' => 'Reporte no encontrado'], 404);
    }
}
