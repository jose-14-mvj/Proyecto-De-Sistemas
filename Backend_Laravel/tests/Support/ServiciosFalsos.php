<?php

namespace Tests\Support;

use App\Services\NotificacionService;
use App\Services\RecordatorioService;
use App\Services\RegistroService;
use App\Services\ReporteService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * SQLite no tiene procedimientos almacenados. Estas clases reemplazan SOLO los
 * métodos protegidos que hacen "CALL sp_..." con inserciones equivalentes, para
 * poder probar controladores, validaciones, permisos y formatos de respuesta.
 * La lógica real de los SP se prueba contra MySQL (Aiven), no aquí.
 */

class RegistroServiceFalso extends RegistroService
{
    protected function crearProdromico(array $p): int
    {
        [$uid, $intensidad, $notas, $sintomas, $fechaHora] = $p;
        $t = Carbon::parse($fechaHora);
        $id = DB::table('registro_sintoma')->insertGetId([
            'id_usuario' => $uid, 'fecha' => $t->toDateString(), 'hora' => $t->format('H:i:s'),
            'intensidad' => $intensidad, 'observacion' => $notas,
        ]);
        foreach (self::ids('sintoma_prodromico', 'id_sintoma', $sintomas) as $s) {
            DB::table('detalle_registro_sintoma')->insert(['id_registro' => $id, 'id_sintoma' => $s]);
        }
        return $id;
    }

    protected function crearEpisodio(array $p): int
    {
        [$uid, $dolor, $duracion, $inicio, $medicacion, $medicamento, $notas, $sintomas, $desenc] = $p;
        $t = Carbon::parse($inicio);
        $id = DB::table('episodio_migrana')->insertGetId([
            'id_usuario' => $uid, 'fecha_inicio' => $t->toDateString(), 'hora_inicio' => $t->format('H:i:s'),
            'duracion' => $duracion, 'nivel_dolor' => $dolor, 'notas' => $notas,
        ]);
        if ($medicacion) {
            DB::table('episodio_medicacion')->insert(['id_episodio' => $id, 'medicamento' => $medicamento]);
        }
        foreach (self::ids('sintoma_prodromico', 'id_sintoma', $sintomas) as $s) {
            DB::table('episodio_sintoma')->insert(['id_episodio' => $id, 'id_sintoma' => $s]);
        }
        foreach (self::ids('desencadenante', 'id_desencadenante', $desenc) as $d) {
            DB::table('episodio_desencadenante')->insert(['id_episodio' => $id, 'id_desencadenante' => $d]);
        }
        return $id;
    }

    public static function ids(string $tabla, string $pk, string $codigos): array
    {
        $lista = array_filter(explode(',', $codigos));
        return $lista ? DB::table($tabla)->whereIn('codigo', $lista)->pluck($pk)->all() : [];
    }
}

class RecordatorioServiceFalso extends RecordatorioService
{
    protected function guardarSp(int $idUsuario, string $frecuencia, bool $activo, string $hora, ?int $dia): void
    {
        DB::table('recordatorio')->updateOrInsert(
            ['id_usuario' => $idUsuario, 'frecuencia' => $frecuencia],
            ['activo' => $activo ? 1 : 0, 'hora_configurada' => $hora, 'dia_semana' => $frecuencia === 'semanal' ? $dia : null],
        );
    }
}

class NotificacionServiceFalso extends NotificacionService
{
    protected function crearSp(array $p): int
    {
        [$uid, $tipo, $mensaje, $idRec] = $p;
        return DB::table('notificacion')->insertGetId([
            'id_usuario' => $uid, 'tipo' => $tipo, 'mensaje' => $mensaje, 'id_recordatorio' => $idRec,
            'fecha_hora' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
    }
}

class ReporteServiceFalso extends ReporteService
{
    protected function llamarSp(array $p): int
    {
        [$uid, $dias, $formato] = $p;
        $fin = Carbon::today();
        if ($dias === 0) {
            $min = collect([
                DB::table('registro_sintoma')->where('id_usuario', $uid)->min('fecha'),
                DB::table('episodio_migrana')->where('id_usuario', $uid)->min('fecha_inicio'),
            ])->filter()->min();
            $ini = $min ? Carbon::parse($min) : $fin->copy();
            $etiqueta = 'Todo el historial';
        } else {
            $ini = $fin->copy()->subDays($dias);
            $etiqueta = [30 => 'Últimos 30 días', 90 => 'Últimos 3 meses', 365 => 'Últimos 12 meses'][$dias] ?? "Últimos $dias días";
        }

        $id = DB::table('reporte')->insertGetId([
            'id_usuario' => $uid, 'formato' => $formato, 'etiqueta' => $etiqueta,
            'periodo_inicio' => $ini->toDateString(), 'periodo_fin' => $fin->toDateString(),
            'fecha_generacion' => Carbon::now()->format('Y-m-d H:i:s'),
        ]);
        foreach (DB::table('registro_sintoma')->where('id_usuario', $uid)->whereBetween('fecha', [$ini->toDateString(), $fin->toDateString()])->pluck('id_registro') as $r) {
            DB::table('reporte_registro')->insert(['id_reporte' => $id, 'id_registro' => $r]);
        }
        foreach (DB::table('episodio_migrana')->where('id_usuario', $uid)->whereBetween('fecha_inicio', [$ini->toDateString(), $fin->toDateString()])->pluck('id_episodio') as $e) {
            DB::table('reporte_episodio')->insert(['id_reporte' => $id, 'id_episodio' => $e]);
        }
        return $id;
    }
}
