<?php

namespace App\Support;

/**
 * Traduce el reporte técnico del ML (/metricas o el guardado en Aiven) a palabras simples:
 * qué tan confiable es y con cuántos datos se entrenó.
 *
 * Es la misma lógica que fiabilidad.py del servicio ML. Se repite aquí para que el panel
 * funcione aunque el ML esté dormido o tenga una versión anterior sin el campo "resumen".
 */
class FiabilidadMl
{
    public const MIN_PERSONAS_REALES = 5;      // mismos mínimos que entrenar.py
    public const MIN_MOMENTOS_REALES = 200;

    /** [nota, semáforo] a partir del AUC */
    public static function nota(?float $auc): array
    {
        if ($auc === null) {
            return ['sin evaluar', 'gris'];
        }
        if ($auc >= 0.90) {
            return ['excelente', 'verde'];
        }
        if ($auc >= 0.80) {
            return ['buena', 'verde'];
        }
        if ($auc >= 0.70) {
            return ['aceptable', 'amarillo'];
        }
        return ['débil', 'rojo'];
    }

    public static function de100(?float $v): ?int
    {
        return $v === null ? null : (int) round($v * 100);
    }

    public static function datos(array $rep): array
    {
        $d = $rep['datos'] ?? [];
        $prueba = $rep['prueba_personas_nuevas']['modelo'] ?? [];
        $simP = (int) ($d['pacientes_simulados'] ?? 0);
        $simF = (int) ($d['filas_simuladas'] ?? 0);
        $reP = (int) ($d['pacientes_reales'] ?? 0);
        $reF = (int) ($d['filas_reales'] ?? 0);
        $total = $simF + $reF;
        $persPrueba = $d['personas_prueba'] ?? ($simP ? (int) round($simP * 0.25) : 0);   // entrenar.py aparta el 25 %

        return [
            'pacientes_simulados' => $simP,
            'momentos_simulados'  => $simF,
            'personas_reales'     => $reP,
            'momentos_reales'     => $reF,
            'momentos_total'      => (int) ($d['filas_modelo_final'] ?? $total),
            'porcentaje_real'     => $total ? round(100 * $reF / $total, 1) : 0.0,
            'personas_prueba'     => (int) $persPrueba,
            'momentos_prueba'     => (int) ($d['filas_prueba'] ?? ($prueba['casos'] ?? 0)),
            'migranas_pct'        => $d['migranas_48h_%'] ?? (isset($prueba['tasa_real']) ? round($prueba['tasa_real'] * 100, 1) : null),
        ];
    }

    public static function resumen(array $rep): array
    {
        $prueba = $rep['prueba_personas_nuevas'] ?? [];
        $auc = isset($prueba['modelo']['auc']) ? (float) $prueba['modelo']['auc'] : null;
        [$nota, $semaforo] = self::nota($auc);
        $datos = self::datos($rep);

        $puntos = [];
        if ($auc !== null) {
            $puntos[] = 'Si se le muestran dos momentos —uno antes de una migraña y otro tranquilo— identifica bien cuál es el de riesgo '
                .self::de100($auc).' de cada 100 veces (al azar serían 50).';
        }
        foreach ($prueba['alertas'] ?? [] as $a) {
            if (abs((float) $a['umbral'] - 0.7) < 1e-9 && $a['aciertos_%'] !== null) {
                $puntos[] = "Cuando marca riesgo «muy alto» (70 % o más), acierta ".round($a['aciertos_%'])
                    .' de cada 100 veces y alcanza a avisar '.round($a['migranas_avisadas_%']).' de cada 100 migrañas.';
            }
        }
        $desvio = self::desvio($prueba['calibracion'] ?? []);
        if ($desvio !== null) {
            $dv = (int) round($desvio);
            $puntos[] = "Sus porcentajes son realistas: en promedio se desvían {$dv} puntos de lo que de verdad pasó "
                .'(si dice 60 %, en la realidad pasa entre '.(60 - $dv).' y '.(60 + $dv).' %).';
        }
        $ref = $prueba['referencia_solo_cuestionario']['auc'] ?? null;
        if ($auc !== null && $ref !== null) {
            $puntos[] = 'Es más preciso que adivinar solo con el cuestionario inicial ('.self::de100((float) $ref).' → '
                .self::de100($auc).' de cada 100): lo que se anota día a día sí ayuda.';
        }

        $real = $rep['prueba_datos_reales'] ?? null;
        if (is_array($real) && isset($real['modelo_con_datos_reales'])) {
            $aucR = (float) $real['modelo_con_datos_reales']['auc'];
            $conReales = ['medido' => true, 'auc' => $aucR, 'nota' => self::nota($aucR)[0],
                'personas' => (int) ($real['personas_de_prueba'] ?? 0),
                'frase' => 'Probado con '.(int) ($real['personas_de_prueba'] ?? 0).' persona(s) real(es) que no vio: '
                    .self::de100($aucR).' de 100 puntos ('.self::nota($aucR)[0].').'];
        } else {
            $fp = max(0, self::MIN_PERSONAS_REALES - $datos['personas_reales']);
            $fm = max(0, self::MIN_MOMENTOS_REALES - $datos['momentos_reales']);
            $falta = implode(' y ', array_filter([$fp ? "$fp persona(s) más" : null, $fm ? "unos $fm momentos más" : null]));
            $conReales = ['medido' => false, 'faltan_personas' => $fp, 'faltan_momentos' => $fm,
                'frase' => 'Todavía no se pudo medir con personas reales'
                    .($falta ? ": faltan $falta con 48 h de seguimiento." : ' (ya hay datos suficientes: se medirá en el próximo entrenamiento).')];
        }

        return [
            'auc' => $auc, 'puntaje' => self::de100($auc), 'nota' => $nota, 'semaforo' => $semaforo,
            'titular' => $auc !== null ? "Fiabilidad {$nota}: ".self::de100($auc).' de 100 puntos' : 'Sin evaluación todavía',
            'puntos' => $puntos, 'reales' => $conReales, 'datos' => $datos,
        ];
    }

    /** Puntos (%) que se equivoca en promedio el porcentaje mostrado */
    private static function desvio(array $cal): ?float
    {
        $total = array_sum(array_map(fn ($c) => (int) ($c['casos'] ?? 0), $cal));
        if (! $total) {
            return null;
        }
        $s = 0.0;
        foreach ($cal as $c) {
            $s += abs((float) $c['prob_media'] - (float) $c['real']) * (int) $c['casos'];
        }
        return $s / $total * 100;
    }
}
