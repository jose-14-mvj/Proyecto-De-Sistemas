<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Cuestionario de migraña (tabla perfil + catálogos).
 * Lectura: vista v_perfil_completo. Escritura: procedimiento sp_guardar_perfil (transaccional).
 */
class PerfilService
{
    /** Devuelve el perfil con las claves que usa el frontend, o null si aún no hizo el cuestionario. */
    public function cargar(int $idUsuario): ?array
    {
        $f = DB::table('v_perfil_completo')->where('id_usuario', $idUsuario)->first();
        if (! $f || $f->id_perfil === null) {
            return null;
        }

        $p = array_filter([
            'genero'            => $f->genero,
            'ocupacion'         => $f->ocupacion,
            'tiempoMigrana'     => $f->antiguedad,
            'frecuencia'        => $f->frecuencia,
            'tieneOrden'        => $f->patron_aparicion,
            'ordenAparicion'    => $f->orden_aparicion,
            'momentoDia'        => $f->inicio_momento_dia,
            'duracion'          => $f->duracion,
            'intensidad'        => $f->intensidad,
            'conocesProdromica' => $f->conoce_fase_prodromica,
            'sueno'             => $f->horas_sueno,
            'estres'            => $f->nivel_estres,
            'cafeina'           => $f->consume_cafeina,
            'porcionesCafeina'  => $f->cantidad_cafeina,
        ], fn ($v) => $v !== null);

        $p['condiciones'] = $f->condiciones ? explode(',', $f->condiciones) : [];
        $p['version']     = 2;
        $p['completedAt'] = str_replace(' ', 'T', (string) $f->fecha_completado);

        return $p;
    }

    /** Guarda o rehace el cuestionario. Lanza QueryException (SQLSTATE 45000) si el SP rechaza algo. */
    public function guardar(int $idUsuario, array $d): void
    {
        $this->llamarSp([
            $idUsuario,
            $d['genero'], $d['ocupacion'], $d['tiempoMigrana'], $d['frecuencia'],
            $d['tieneOrden'], $d['ordenAparicion'] ?? null,
            $d['momentoDia'], $d['duracion'], $d['intensidad'], $d['conocesProdromica'],
            $d['sueno'], $d['estres'],
            $d['cafeina'], $d['porcionesCafeina'] ?? null,
            implode(',', $d['condiciones']),
        ]);
    }

    protected function llamarSp(array $parametros): void
    {
        DB::statement('CALL sp_guardar_perfil('.implode(',', array_fill(0, 16, '?')).')', $parametros);
    }
}
