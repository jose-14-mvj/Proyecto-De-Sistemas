<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/** Notificaciones del usuario. Escritura: sp_crear_notificacion / sp_marcar_notificacion. */
class NotificacionService
{
    public const ESTADOS = ['pendiente', 'enviada', 'leida', 'descartada'];

    public function listar(int $idUsuario, string $estado = 'todas', int $limite = 50): array
    {
        $q = DB::table('notificacion')->where('id_usuario', $idUsuario);
        if (in_array($estado, self::ESTADOS, true)) {
            $q->where('estado', $estado);
        }

        return $q->orderByDesc('fecha_hora')->orderByDesc('id_notificacion')->limit($limite)->get()
            ->map(fn ($n) => $this->aFrontend($n))->all();
    }

    public function pendientes(int $idUsuario): int
    {
        return DB::table('notificacion')->where('id_usuario', $idUsuario)->where('estado', 'pendiente')->count();
    }

    public function obtener(int $idUsuario, int $id): ?array
    {
        $n = DB::table('notificacion')->where('id_usuario', $idUsuario)->where('id_notificacion', $id)->first();
        return $n ? $this->aFrontend($n) : null;
    }

    public function crear(int $idUsuario, string $tipo, string $mensaje, ?int $idRecordatorio): int
    {
        return $this->crearSp([$idUsuario, $tipo, $mensaje, $idRecordatorio]);
    }

    public function marcar(int $idUsuario, int $id, string $estado): void
    {
        DB::statement('CALL sp_marcar_notificacion(?,?,?)', [$idUsuario, $id, $estado]);
    }

    protected function crearSp(array $p): int
    {
        DB::statement('CALL sp_crear_notificacion(?,?,?,?,@id_notificacion)', $p);
        return (int) DB::selectOne('SELECT @id_notificacion AS id')->id;
    }

    private function aFrontend(object $n): array
    {
        return [
            'id'              => (int) $n->id_notificacion,
            'tipo'            => $n->tipo,
            'mensaje'         => $n->mensaje,
            'estado'          => $n->estado,
            'id_recordatorio' => $n->id_recordatorio === null ? null : (int) $n->id_recordatorio,
            'fecha'           => Carbon::parse($n->fecha_hora)->format('Y-m-d\TH:i:s'),   // hora de Bolivia
        ];
    }
}
