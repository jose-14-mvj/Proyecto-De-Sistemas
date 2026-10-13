<?php

namespace App\Http\Controllers;

use App\Http\Requests\CrearNotificacionRequest;
use App\Http\Requests\MarcarNotificacionRequest;
use App\Services\NotificacionService;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function __construct(private NotificacionService $notificaciones)
    {
    }

    /** GET /api/notificaciones?estado=todas|pendiente|enviada|leida|descartada&limite=50 */
    public function index(Request $request): JsonResponse
    {
        $d = $request->validate([
            'estado' => ['nullable', 'in:todas,pendiente,enviada,leida,descartada'],
            'limite' => ['nullable', 'integer', 'between:1,200'],
        ]);
        $uid = $request->user()->id_usuario;

        return response()->json([
            'ok'             => true,
            'pendientes'     => $this->notificaciones->pendientes($uid),
            'notificaciones' => $this->notificaciones->listar($uid, $d['estado'] ?? 'todas', (int) ($d['limite'] ?? 50)),
        ]);
    }

    /** POST /api/notificaciones */
    public function crear(CrearNotificacionRequest $request): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        $d = $request->validated();

        try {
            $id = $this->notificaciones->crear($uid, $d['tipo'], $d['mensaje'], $d['id_recordatorio'] ?? null);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? '') === '45000') {
                return response()->json(['error' => $e->errorInfo[2] ?? 'No se pudo guardar'], 422);
            }
            throw $e;
        }

        return response()->json(['ok' => true, 'notificacion' => $this->notificaciones->obtener($uid, $id)], 201);
    }

    /** PUT /api/notificaciones/{id} — marca enviada / leida / descartada */
    public function marcar(MarcarNotificacionRequest $request, int $id): JsonResponse
    {
        $uid = $request->user()->id_usuario;
        if (! $this->notificaciones->obtener($uid, $id)) {      // el SP no avisa si no es del usuario: se comprueba antes
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
        $this->notificaciones->marcar($uid, $id, $request->validated('estado'));

        return response()->json(['ok' => true, 'notificacion' => $this->notificaciones->obtener($uid, $id)]);
    }
}
