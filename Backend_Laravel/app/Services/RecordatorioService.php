<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Recordatorios (diario / semanal) y preferencia de alerta de riesgo.
 * Escritura: sp_guardar_recordatorio. Lectura: tabla recordatorio + usuario.alerta_riesgo.
 * Formato = el de js/recordatorios.js: { daily:{on,time}, weekly:{on,day,time}, alerta:{on} }
 */
class RecordatorioService
{
    public function obtener(int $idUsuario): array
    {
        $filas = DB::table('recordatorio')->where('id_usuario', $idUsuario)->get();
        $d = $filas->first(fn ($r) => $r->frecuencia === 'diaria');
        $w = $filas->first(fn ($r) => $r->frecuencia === 'semanal');
        $alerta = DB::table('usuario')->where('id_usuario', $idUsuario)->value('alerta_riesgo');

        return [
            'daily'  => ['on' => $d ? (bool) $d->activo : true,  'time' => $d ? substr($d->hora_configurada, 0, 5) : '20:00'],
            'weekly' => ['on' => $w ? (bool) $w->activo : true,  'day' => $w ? (int) $w->dia_semana : 0,
                         'time' => $w ? substr($w->hora_configurada, 0, 5) : '19:00'],
            'alerta' => ['on' => $alerta === null ? true : (bool) $alerta],
        ];
    }

    /** Actualización parcial: solo cambia lo que llega; lo demás conserva su valor actual. */
    public function guardar(int $idUsuario, array $d): array
    {
        $actual = $this->obtener($idUsuario);

        DB::transaction(function () use ($idUsuario, $d, $actual) {
            if (isset($d['daily'])) {
                $x = array_merge($actual['daily'], $d['daily']);
                $this->guardarSp($idUsuario, 'diaria', (bool) $x['on'], $x['time'].':00', null);
            }
            if (isset($d['weekly'])) {
                $x = array_merge($actual['weekly'], $d['weekly']);
                $this->guardarSp($idUsuario, 'semanal', (bool) $x['on'], $x['time'].':00', (int) $x['day']);
            }
            if (isset($d['alerta']['on'])) {
                DB::table('usuario')->where('id_usuario', $idUsuario)->update(['alerta_riesgo' => $d['alerta']['on'] ? 1 : 0]);
            }
        });

        return $this->obtener($idUsuario);
    }

    protected function guardarSp(int $idUsuario, string $frecuencia, bool $activo, string $hora, ?int $dia): void
    {
        DB::statement('CALL sp_guardar_recordatorio(?,?,?,?,?)', [$idUsuario, $frecuencia, $activo ? 1 : 0, $hora, $dia]);
    }
}
