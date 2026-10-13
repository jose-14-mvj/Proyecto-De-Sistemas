<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas del dashboard. Solo lectura, a partir de las vistas:
 *   v_resumen_30dias, v_episodios_semana, v_desencadenantes_frecuentes,
 *   v_sintomas_frecuentes y v_historial.
 * Las claves de `patrones` son las mismas que calcula hoy el frontend
 * (MSAnalisis.patrones en js/analisis.js), para poder reemplazarlo sin
 * cambiar las pantallas.
 */
class EstadisticaService
{
    private const DIA = 86400;

    /** Tarjetas del dashboard (últimos 30 días + total histórico). */
    public function resumen(int $idUsuario): array
    {
        $f = DB::table('v_resumen_30dias')->where('id_usuario', $idUsuario)->first();

        return [
            'episodios30'      => (int) ($f->episodios_30d ?? 0),
            'prodromicos30'    => (int) ($f->prodromicos_30d ?? 0),
            'intensidadMedia30' => isset($f->intensidad_media_30d) ? (float) $f->intensidad_media_30d : null,
            'registrosTotales' => (int) ($f->registros_totales ?? 0),
        ];
    }

    /**
     * Episodios por semana (lunes a domingo) de las últimas $n semanas, de la más antigua
     * a la actual. Las semanas sin episodios salen con 0 para que el gráfico no tenga huecos.
     */
    public function semanas(int $idUsuario, int $n = 12): array
    {
        $lunesActual = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $desde = $lunesActual->copy()->subWeeks($n - 1);

        $filas = DB::table('v_episodios_semana')
            ->where('id_usuario', $idUsuario)
            ->where('semana_inicio', '>=', $desde->toDateString())
            ->get()
            ->keyBy(fn ($f) => Carbon::parse($f->semana_inicio)->toDateString());

        $salida = [];
        for ($i = 0; $i < $n; $i++) {
            $clave = $desde->copy()->addWeeks($i)->toDateString();
            $f = $filas->get($clave);
            $salida[] = [
                'inicio'     => $clave,
                'episodios'  => (int) ($f->episodios ?? 0),
                'dolorMedio' => $f ? (float) $f->dolor_medio : null,
            ];
        }

        return $salida;
    }

    /** Desencadenantes más frecuentes (en episodios): [{codigo, nombre, n}] */
    public function desencadenantes(int $idUsuario, int $top = 5): array
    {
        return DB::table('v_desencadenantes_frecuentes')
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('veces')->orderBy('nombre')
            ->limit($top)->get()
            ->map(fn ($f) => ['codigo' => $f->codigo, 'nombre' => $f->nombre, 'n' => (int) $f->veces])
            ->all();
    }

    /** Síntomas más frecuentes (prodrómicos + episodios): [{codigo, nombre, n}] */
    public function sintomas(int $idUsuario, int $top = 5): array
    {
        return DB::table('v_sintomas_frecuentes')
            ->where('id_usuario', $idUsuario)
            ->orderByDesc('veces')->orderBy('nombre_sintoma')
            ->limit($top)->get()
            ->map(fn ($f) => ['codigo' => $f->codigo, 'nombre' => $f->nombre_sintoma, 'n' => (int) $f->veces])
            ->all();
    }

    /**
     * Patrones de todo el historial (misma lógica que MSAnalisis.patrones):
     * total, episodios, suficiente, intensidadMedia, duracionFrecuente (código), intervaloMedio (días),
     * porDia [dom..sáb], medicacionPct, tendencia {actual, previo, direccion}.
     */
    public function patrones(int $idUsuario): array
    {
        // Nota: el tipo se filtra en PHP (ver RegistroService::listar sobre la colación de v_historial)
        $filas = DB::table('v_historial')
            ->where('id_usuario', $idUsuario)
            ->get(['tipo', 'fecha_hora', 'intensidad', 'duracion', 'medicacion']);

        $eps = $filas->filter(fn ($f) => $f->tipo === 'episodio')->values();

        $out = [
            'total'             => $filas->count(),
            'episodios'         => $eps->count(),
            'suficiente'        => $filas->count() >= 3,
            'intensidadMedia'   => null,
            'duracionFrecuente' => null,
            'intervaloMedio'    => null,
            'porDia'            => [0, 0, 0, 0, 0, 0, 0],
            'medicacionPct'     => null,
            'tendencia'         => null,
        ];
        if ($eps->isEmpty()) {
            return $out;
        }

        $ints = $eps->pluck('intensidad')->map(fn ($v) => (int) $v)->filter();
        if ($ints->isNotEmpty()) {
            $out['intensidadMedia'] = round($ints->avg(), 1);
        }

        // Duración más frecuente (empate: orden alfabético, como el frontend)
        $dur = $eps->pluck('duracion')->filter()->countBy()->all();
        if ($dur) {
            uksort($dur, fn ($a, $b) => ($dur[$b] <=> $dur[$a]) ?: strcmp($a, $b));
            $out['duracionFrecuente'] = array_key_first($dur);
        }

        $tiempos = $eps->map(fn ($f) => Carbon::parse($f->fecha_hora)->getTimestamp())->sort()->values();

        // Días promedio entre episodios (mínimo 3 episodios; se ignoran huecos < 12 h)
        if ($tiempos->count() >= 3) {
            $gaps = [];
            for ($i = 1; $i < $tiempos->count(); $i++) {
                $g = ($tiempos[$i] - $tiempos[$i - 1]) / self::DIA;
                if ($g >= 0.5) {
                    $gaps[] = $g;
                }
            }
            if ($gaps) {
                $out['intervaloMedio'] = round(array_sum($gaps) / count($gaps), 1);
            }
        }

        foreach ($eps as $f) {
            $out['porDia'][(int) Carbon::parse($f->fecha_hora)->dayOfWeek]++;   // 0 = domingo, como getDay()
        }

        $out['medicacionPct'] = (int) round(100 * $eps->filter(fn ($f) => (int) $f->medicacion > 0)->count() / $eps->count());

        // Tendencia: últimos 30 días frente a los 30 anteriores
        $ahora = Carbon::now()->getTimestamp();
        $en = fn (int $d0, int $d1) => $tiempos->filter(function ($t) use ($ahora, $d0, $d1) {
            $d = ($ahora - $t) / self::DIA;
            return $d >= $d0 && $d < $d1;
        })->count();
        $act = $en(0, 30);
        $prev = $en(30, 60);
        if ($act + $prev >= 2) {
            $out['tendencia'] = [
                'actual'    => $act,
                'previo'    => $prev,
                'direccion' => $act > $prev ? 'aumentó' : ($act < $prev ? 'disminuyó' : 'igual'),
            ];
        }

        return $out;
    }
}
