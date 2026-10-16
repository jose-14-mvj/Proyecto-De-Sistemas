<?php

namespace App\Support;

/**
 * Arma el HTML (un solo archivo, sin internet) del panel local del ML.
 * Recibe los datos ya juntados por el comando migrasense:panel-ml.
 */
class PanelMlHtml
{
    /** Nombres entendibles de las variables del modelo (caracteristicas.py) */
    private const NOMBRES = [
        'tiene_perfil' => 'Llenó el cuestionario inicial',
        'ataques_mes_perfil' => 'Ataques al mes (según cuestionario)',
        'sueno_ord' => 'Calidad de sueño (cuestionario)',
        'estres_ord' => 'Nivel de estrés (cuestionario)',
        'cafeina_ord' => 'Consumo de cafeína (cuestionario)',
        'antiguedad_ord' => 'Años con migraña (cuestionario)',
        'mujer' => 'Sexo femenino',
        'cond_hormonal' => 'Condición hormonal',
        'cond_sueno' => 'Trastorno del sueño',
        'cond_ansiedad' => 'Ansiedad',
        'episodios_total' => 'Episodios registrados (total)',
        'episodios_30d' => 'Episodios en los últimos 30 días',
        'episodios_90d' => 'Episodios en los últimos 90 días',
        'dias_desde_ultimo_episodio' => 'Días desde la última migraña',
        'dias_observados' => 'Días usando la app',
        'tasa_mensual_personal' => 'Migrañas por mes de la persona',
        'intervalo_medio_dias' => 'Días promedio entre migrañas',
        'ciclo_relativo' => 'Punto de su ciclo personal entre migrañas',
        'frac_desenc_estres' => 'Desencadenante: estrés',
        'frac_desenc_sueno' => 'Desencadenante: sueño',
        'frac_desenc_hormonal' => 'Desencadenante: hormonal',
        'avisos_evaluables' => 'Avisos anteriores evaluables',
        'precision_avisos_personal' => 'Qué tan seguido sus síntomas avisaron bien',
        'registros_24h' => 'Síntomas anotados en 24 h',
        'registros_48h' => 'Síntomas anotados en 48 h',
        'horas_desde_ultimo_registro' => 'Horas desde el último síntoma anotado',
        'intensidad_max_48h' => 'Intensidad máxima de síntomas (48 h)',
        'sintomas_distintos_48h' => 'Síntomas distintos en 48 h',
        'hora_del_dia' => 'Hora del día',
        'dia_semana' => 'Día de la semana',
    ];

    public static function generar(array $d): string
    {
        $salud = $d['salud'] ?? null;
        $met = $d['metricas'] ?? null;
        $hist = $d['historial'] ?? [];
        $c = $d['conteos'] ?? [];
        $r = $d['reglas'];

        $activo = null;
        foreach ($hist as $f) {
            if ($f['estado'] === 'activo') {
                $activo = $f;
            }
        }
        $ultimo = $hist ? end($hist) : null;

        $html = '<header><div><h1>Aprendizaje del ML</h1><p class="sub">MigraSense · panel local, solo para ti · '
            .self::e($d['generado']).'</p></div></header>';

        if (! empty($d['aviso_ml'])) {
            $html .= '<div class="aviso">'.self::e(rtrim($d['aviso_ml'], '. ')).'. Las secciones del ML quedan vacías; lo de la base de datos sí está.</div>';
        }

        // ---------- Resumen ----------
        $aprendio = $salud ? ! empty($salud['aprendido_con_usuarios']) : ($activo !== null);
        $html .= '<section class="kpis">'
            .self::kpi('Modelo en uso', $salud['version'] ?? ($activo['version'] ?? '2.0.0'),
                $aprendio ? 'Aprendido con tus compañeros' : 'Modelo base (libros)', $aprendio ? 'ok' : 'neutro')
            .self::kpi('Acierto (AUC)', isset($salud['auc_personas_nuevas']) ? self::num($salud['auc_personas_nuevas']) : ($activo ? self::num($activo['auc']) : '—'),
                '0,5 = azar · 1 = perfecto')
            .self::kpi('Entrenamientos', (string) count($hist),
                self::contar($hist, 'activo') + self::contar($hist, 'reemplazado').' aceptados · '.self::contar($hist, 'rechazado').' rechazados')
            .self::kpi('Personas reales usadas', (string) ($salud['pacientes_reales_usados'] ?? ($activo['pacientes_reales'] ?? 0)),
                'de '.($c['personas_datos'] ?? 0).' con datos hoy')
            .'</section>';

        // ---------- Próximo aprendizaje ----------
        $total = (int) ($c['total'] ?? 0);
        $usados = (int) ($ultimo['registros_usados'] ?? 0);
        $nuevos = max(0, $total - $usados);
        $horasPasadas = $ultimo ? max(0, (time() - strtotime($ultimo['fecha_inicio'])) / 3600) : null;
        $okReg = $nuevos >= $r['minimo'];
        $okHoras = $horasPasadas === null || $horasPasadas >= $r['horas'];
        $enCurso = $ultimo && $ultimo['estado'] === 'en_curso';

        if ($enCurso) {
            $estadoProx = 'Hay un entrenamiento en curso ahora mismo (empezó '.self::fecha($ultimo['fecha_inicio']).').';
        } elseif (! $r['auto']) {
            $estadoProx = 'El aprendizaje automático está APAGADO (ML_AUTO_REENTRENAR=false). Solo aprende si lo fuerzas.';
        } elseif ($okReg && $okHoras) {
            $estadoProx = 'Ya cumple las condiciones: aprenderá la próxima vez que alguien abra el dashboard.';
        } else {
            $estadoProx = 'Todavía no le toca. Falta: '.implode(' y ', array_filter([
                $okReg ? null : ($r['minimo'] - $nuevos).' registro(s) nuevo(s)',
                $okHoras ? null : self::horas($r['horas'] - $horasPasadas).' de espera',
            ])).'.';
        }
        $html .= '<section><h2>¿Cuándo vuelve a aprender?</h2><p class="nota">Aprende solo cuando se cumplen las dos condiciones y alguien abre el dashboard.</p>'
            .self::progreso('Registros nuevos desde el último entrenamiento', $nuevos, $r['minimo'], $nuevos.' de '.$r['minimo'])
            .self::progreso('Horas desde el último entrenamiento', $horasPasadas ?? $r['horas'], $r['horas'],
                $horasPasadas === null ? 'nunca entrenó' : self::horas($horasPasadas).' de '.$r['horas'].' h')
            .'<p class="estado">'.self::e($estadoProx).'</p></section>';

        // ---------- Datos ----------
        $act = $d['actividad'] ?? [];
        $sum30 = array_sum(array_map(fn ($x) => $x['registros'] + $x['episodios'], $act));
        $html .= '<section><h2>Datos que tus compañeros cargaron</h2><div class="mini">'
            .self::dato('Usuarios', $c['usuarios'] ?? 0, ($c['usuarios_demo'] ?? 0) ? ($c['usuarios_demo'].' son de demostración') : '')
            .self::dato('Con datos', $c['personas_datos'] ?? 0, 'personas que anotaron algo')
            .self::dato('Síntomas', $c['registros'] ?? 0)
            .self::dato('Episodios', $c['episodios'] ?? 0)
            .'</div><h3>Cargados por día (últimos 30 días) · '.$sum30.' en total</h3>'
            .self::actividad($act).'</section>';

        // ---------- Historial ----------
        $html .= '<section><h2>Historial de entrenamientos</h2>';
        if (! $hist) {
            $html .= '<p class="vacio">Todavía no hubo ningún entrenamiento: la app usa el modelo de los libros.</p>';
        } else {
            $html .= '<h3>Acierto (AUC) de cada entrenamiento</h3>'.self::lineaAuc($hist).self::tablaHistorial($hist);
        }
        $html .= '</section>';

        // ---------- Memoria en Aiven ----------
        if ($hist) {
            $html .= self::seccionMemoria($hist);
        }

        // ---------- Métricas del modelo en uso ----------
        if ($met) {
            $html .= self::seccionMetricas($met, ! empty($d['metricas_de_aiven']));
        }

        return self::pagina($html);
    }

    // =====================================================================
    // Secciones
    // =====================================================================
    private static function seccionMemoria(array $hist): string
    {
        $h = '<section><h2>Memoria guardada en Aiven</h2>'
            .'<p class="nota">Tabla <code>modelo_ml</code>. Cada modelo aceptado se guarda entero en la columna <code>archivo</code> '
            .'(el modelo en base64), con su huella <code>sha256</code> para comprobar que no se dañó, y lo que aprendió en texto legible en <code>reporte</code>. '
            .'Si Render reinicia el ML, Laravel le devuelve el modelo desde aquí. Los datos reales de tus compañeros no se guardan en el ML: '
            .'siguen en sus tablas de Aiven (<code>registro_sintoma</code>, <code>episodio_migrana</code>…).</p>'
            .'<table><thead><tr><th>#</th><th>Versión</th><th>Estado</th><th>Tamaño</th><th>Archivo sano</th><th>Lo aprendido</th><th>Personas reales</th></tr></thead><tbody>';
        foreach (array_reverse($hist) as $f) {
            $sano = ! array_key_exists('sano', $f) || $f['sano'] === null
                ? '<span class="gris">sin archivo</span>'
                : ($f['sano'] ? '<span class="bien">✔ verificado</span>' : '<span class="malo">✘ dañado</span>');
            $rep = $f['reporte'] ?? null;
            $h .= '<tr><td>'.(int) $f['id_modelo'].'</td><td class="nw">'.self::e((string) ($f['version'] ?? '—')).'</td>'
                .'<td><span class="chip '.self::e($f['estado']).'">'.self::e(self::estado($f['estado'])).'</span></td>'
                .'<td class="nw">'.(isset($f['kb']) && $f['kb'] !== null ? self::num($f['kb'], 1).' KB' : '—').'</td>'
                .'<td>'.$sano.'</td>'
                .'<td>'.($rep ? '<span class="bien">guardado</span>' : '<span class="gris">—</span>').'</td>'
                .'<td>'.self::e((string) ($rep['datos']['pacientes_reales'] ?? ($f['pacientes_reales'] ?? '—'))).'</td></tr>';
        }
        return $h.'</tbody></table><p class="nota">Los entrenamientos rechazados o con error no guardan archivo: no se usan, así que no hace falta.</p></section>';
    }

    private static function seccionMetricas(array $m, bool $deAiven = false): string
    {
        $h = '<section><h2>Qué aprendió el modelo en uso</h2>';
        if ($deAiven) {
            $h .= '<p class="aviso">El ML no respondió: esto se leyó de la memoria guardada en Aiven.</p>';
        }
        $datos = $m['datos'] ?? [];
        $h .= '<p class="nota">Entrenado el '.self::e(self::fecha(str_replace('T', ' ', (string) ($m['entrenado'] ?? '')))).' con '
            .(int) ($datos['pacientes_simulados'] ?? 0).' pacientes simulados de la literatura médica y <b>'
            .(int) ($datos['pacientes_reales'] ?? 0).' personas reales</b> ('.(int) ($datos['filas_reales'] ?? 0).' momentos). Predice migraña en las próximas '
            .(int) ($m['horizonte_horas'] ?? 48).' h.</p>';

        // Importancia
        $imp = $m['lo_que_mas_pesa'] ?? [];
        if ($imp) {
            $items = array_map(fn ($x) => [self::nombre($x['caracteristica']), (float) $x['importancia'],
                self::num($x['importancia'], 3)], $imp);
            $h .= '<h3>Lo que más pesa en la predicción</h3><p class="nota">Cuánto empeora el acierto si se le quita cada dato. Más larga = más importante.</p>'
                .self::barras($items);
        }

        // Datos reales vs base
        $pr = $m['prueba_datos_reales'] ?? null;
        $h .= '<h3>¿Aprender de ustedes lo mejoró?</h3>';
        if (is_array($pr)) {
            $filas = [
                ['Solo cuestionario', $pr['referencia_solo_cuestionario']['auc'] ?? null],
                ['Modelo de los libros', $pr['modelo_base_estudios']['auc'] ?? null],
                ['Libros + tus compañeros', $pr['modelo_con_datos_reales']['auc'] ?? null],
            ];
            $items = array_map(fn ($f) => [$f[0], (float) $f[1], self::num($f[1])], array_filter($filas, fn ($f) => $f[1] !== null));
            $h .= '<p class="nota">Probado con '.(int) ($pr['personas_de_prueba'] ?? 0).' personas reales que el modelo NO vio al entrenar (AUC: más alto = mejor).</p>'
                .self::barras(array_values($items), 1.0);
            $base = $pr['modelo_base_estudios']['auc'] ?? null;
            $real = $pr['modelo_con_datos_reales']['auc'] ?? null;
            if ($base !== null && $real !== null) {
                $dif = $real - $base;
                $h .= '<p class="estado">'.($dif > 0.005 ? 'Sí: con sus datos acierta mejor (+'.self::num($dif).').'
                    : ($dif < -0.005 ? 'Por ahora no: acierta un poco peor ('.self::num($dif).'). Hacen falta más datos.' : 'Por ahora acierta igual. Hacen falta más datos.')).'</p>';
            }
        } else {
            $h .= '<p class="vacio">'.self::e(is_string($pr) ? $pr : 'Sin datos todavía.').'</p>';
        }

        $prueba = $m['prueba_personas_nuevas'] ?? [];

        // Calibración
        $cal = $prueba['calibracion'] ?? [];
        if ($cal) {
            $h .= '<h3>¿Cuando dice X %, pasa de verdad?</h3><p class="nota">Barra clara: lo que predijo. Barra oscura: lo que pasó de verdad. Si se parecen, el porcentaje es confiable.</p>'
                .self::calibracion($cal);
        }

        // Alertas
        $al = $prueba['alertas'] ?? [];
        if ($al) {
            $h .= '<h3>Si se manda alerta desde cierto porcentaje</h3><table><thead><tr><th>Alerta desde</th><th>De cada 100 alertas, aciertan</th><th>Migrañas que avisó a tiempo</th></tr></thead><tbody>';
            foreach ($al as $a) {
                $h .= '<tr><td>'.round($a['umbral'] * 100).' %</td><td>'.self::num($a['aciertos_%'], 1).'</td><td>'.self::num($a['migranas_avisadas_%'], 1).' %</td></tr>';
            }
            $h .= '</tbody></table>';
        }

        // Comparación con referencias
        if (isset($prueba['modelo'])) {
            $refs = [
                ['Solo cuestionario', $prueba['referencia_solo_cuestionario']['auc'] ?? null],
                ['Modelo lineal simple', $prueba['referencia_lineal']['auc'] ?? null],
                ['Este modelo', $prueba['modelo']['auc'] ?? null],
            ];
            $items = array_map(fn ($f) => [$f[0], (float) $f[1], self::num($f[1])], array_filter($refs, fn ($f) => $f[1] !== null));
            $h .= '<h3>Comparado con métodos más simples</h3><p class="nota">Probado con '.(int) ($prueba['modelo']['casos'] ?? 0).' momentos de personas nuevas.</p>'
                .self::barras(array_values($items), 1.0);
        }

        return $h.'</section>';
    }

    private static function tablaHistorial(array $hist): string
    {
        $h = '<table><thead><tr><th>#</th><th>Inicio</th><th>Estado</th><th>AUC</th><th>Antes</th><th>Personas</th><th>Datos</th><th>Motivo</th></tr></thead><tbody>';
        foreach (array_reverse($hist) as $f) {
            $h .= '<tr><td>'.(int) $f['id_modelo'].'</td><td class="nw">'.self::e(self::fecha($f['fecha_inicio'])).'</td>'
                .'<td><span class="chip '.self::e($f['estado']).'">'.self::e(self::estado($f['estado'])).'</span></td>'
                .'<td>'.self::num($f['auc']).'</td><td>'.self::num($f['auc_anterior']).'</td>'
                .'<td>'.self::e((string) ($f['pacientes_reales'] ?? '—')).'</td><td>'.self::e((string) ($f['registros_usados'] ?? '—')).'</td>'
                .'<td class="motivo">'.self::e((string) ($f['motivo'] ?? '')).'</td></tr>';
        }
        return $h.'</tbody></table>';
    }

    // =====================================================================
    // Gráficos (SVG simple)
    // =====================================================================
    /** Barras horizontales: [[etiqueta, valor, texto], ...] */
    private static function barras(array $items, ?float $max = null): string
    {
        $max = $max ?? max(array_map(fn ($i) => $i[1], $items) ?: [1]);
        $max = $max > 0 ? $max : 1;
        $h = '<div class="barras">';
        foreach ($items as [$et, $v, $txt]) {
            $p = max(0, min(100, $v / $max * 100));
            $h .= '<div class="fila"><span class="et">'.self::e($et).'</span><span class="pista"><span class="b" style="width:'
                .round($p, 1).'%"></span></span><span class="v">'.self::e($txt).'</span></div>';
        }
        return $h.'</div>';
    }

    private static function progreso(string $et, float $v, float $meta, string $txt): string
    {
        $p = $meta > 0 ? max(0, min(100, $v / $meta * 100)) : 100;
        return '<div class="prog"><div class="pet"><span>'.self::e($et).'</span><b>'.self::e($txt).'</b></div><div class="pista"><span class="b'
            .($p >= 100 ? ' lleno' : '').'" style="width:'.round($p, 1).'%"></span></div></div>';
    }

    private static function actividad(array $dias): string
    {
        if (! $dias) {
            return '<p class="vacio">Sin datos.</p>';
        }
        $max = max(1, max(array_map(fn ($x) => $x['registros'] + $x['episodios'], $dias)));
        $n = count($dias);
        $w = 720; $alto = 140; $base = 120; $paso = $w / $n; $ancho = max(2, $paso - 4);
        $svg = '<svg viewBox="0 0 '.$w.' '.$alto.'" class="grafico" role="img" aria-label="Datos cargados por día">';
        $svg .= '<line x1="0" y1="'.$base.'" x2="'.$w.'" y2="'.$base.'" class="eje"/>';
        $i = 0;
        foreach ($dias as $fecha => $x) {
            $xr = $i * $paso + 2;
            $hr = $x['registros'] / $max * ($base - 14);
            $he = $x['episodios'] / $max * ($base - 14);
            $titulo = date('d/m', strtotime($fecha)).': '.$x['registros'].' síntomas, '.$x['episodios'].' episodios';
            $svg .= '<g><title>'.self::e($titulo).'</title>';
            if ($hr > 0) {
                $svg .= '<rect x="'.round($xr, 1).'" y="'.round($base - $hr, 1).'" width="'.round($ancho, 1).'" height="'.round($hr, 1).'" class="s1" rx="2"/>';
            }
            if ($he > 0) {
                $svg .= '<rect x="'.round($xr, 1).'" y="'.round($base - $hr - $he, 1).'" width="'.round($ancho, 1).'" height="'.round($he, 1).'" class="s2" rx="2"/>';
            }
            $svg .= '<rect x="'.round($xr, 1).'" y="0" width="'.round($ancho, 1).'" height="'.$base.'" fill="transparent"/></g>';
            if (($i % 7 === 0 && $i < $n - 4) || $i === $n - 1) {
                [$tx, $anc] = $i === 0 ? [$xr, 'start'] : ($i === $n - 1 ? [$xr + $ancho, 'end'] : [$xr + $ancho / 2, 'middle']);
                $svg .= '<text x="'.round($tx, 1).'" y="136" class="tx" text-anchor="'.$anc.'">'.date('d/m', strtotime($fecha)).'</text>';
            }
            $i++;
        }
        $svg .= '<text x="0" y="10" class="tx">máx. '.$max.' en un día</text></svg>';
        return $svg.'<div class="leyenda"><span><i class="s1"></i>Síntomas</span><span><i class="s2"></i>Episodios</span><span class="nota">Pasa el mouse sobre una barra para ver el día.</span></div>';
    }

    private static function lineaAuc(array $hist): string
    {
        $pts = array_values(array_filter($hist, fn ($f) => $f['auc'] !== null));
        if (! $pts) {
            return '<p class="vacio">Ningún entrenamiento terminó con resultado todavía.</p>';
        }
        $vals = array_map(fn ($f) => (float) $f['auc'], $pts);
        foreach ($pts as $f) {
            if ($f['auc_anterior'] !== null) {
                $vals[] = (float) $f['auc_anterior'];
            }
        }
        $min = max(0.5, floor((min($vals) - 0.02) * 50) / 50);
        $max = min(1.0, ceil((max($vals) + 0.02) * 50) / 50);
        if ($max - $min < 0.04) {
            $max = min(1, $min + 0.04);
        }
        $w = 720; $alto = 180; $izq = 44; $der = 16; $arr = 14; $abj = 30;
        $n = count($pts);
        $x = fn ($i) => $n === 1 ? ($izq + ($w - $izq - $der) / 2) : $izq + $i * ($w - $izq - $der) / ($n - 1);
        $y = fn ($v) => $arr + (1 - ($v - $min) / ($max - $min)) * ($alto - $arr - $abj);

        $svg = '<svg viewBox="0 0 '.$w.' '.$alto.'" class="grafico" role="img" aria-label="AUC por entrenamiento">';
        foreach ([$min, ($min + $max) / 2, $max] as $g) {
            $svg .= '<line x1="'.$izq.'" x2="'.($w - $der).'" y1="'.round($y($g), 1).'" y2="'.round($y($g), 1).'" class="rejilla"/>'
                .'<text x="'.($izq - 6).'" y="'.round($y($g) + 4, 1).'" class="tx" text-anchor="end">'.self::num($g).'</text>';
        }
        // Referencia: el modelo base (auc_anterior del primero)
        if ($pts[0]['auc_anterior'] !== null) {
            $yb = round($y((float) $pts[0]['auc_anterior']), 1);
            $svg .= '<line x1="'.$izq.'" x2="'.($w - $der).'" y1="'.$yb.'" y2="'.$yb.'" class="ref"/><text x="'.($w - $der).'" y="'.($yb - 5).'" class="tx" text-anchor="end">modelo de los libros '.self::num($pts[0]['auc_anterior']).'</text>';
        }
        $linea = [];
        foreach ($pts as $i => $f) {
            $linea[] = round($x($i), 1).','.round($y((float) $f['auc']), 1);
        }
        if ($n > 1) {
            $svg .= '<polyline points="'.implode(' ', $linea).'" class="linea"/>';
        }
        foreach ($pts as $i => $f) {
            $svg .= '<g><title>'.self::e('#'.$f['id_modelo'].' · '.self::estado($f['estado']).' · AUC '.self::num($f['auc']).' · '.($f['pacientes_reales'] ?? '?').' personas').'</title>'
                .'<circle cx="'.round($x($i), 1).'" cy="'.round($y((float) $f['auc']), 1).'" r="6" class="p '.self::e($f['estado']).'"/></g>'
                .'<text x="'.round($x($i), 1).'" y="'.($alto - 10).'" class="tx" text-anchor="middle">#'.(int) $f['id_modelo'].' · '.date('d/m', strtotime($f['fecha_inicio'])).'</text>';
        }
        return $svg.'</svg><div class="leyenda"><span><i class="p activo"></i>En uso</span><span><i class="p reemplazado"></i>Aceptado antes</span><span><i class="p rechazado"></i>Rechazado</span></div>';
    }

    private static function calibracion(array $cal): string
    {
        $n = count($cal);
        $w = 720; $alto = 170; $base = 140; $izq = 30; $paso = ($w - $izq) / $n; $ancho = ($paso - 10) / 2;
        $svg = '<svg viewBox="0 0 '.$w.' '.$alto.'" class="grafico" role="img" aria-label="Calibración">';
        foreach ([0, 0.5, 1] as $g) {
            $yy = round($base - $g * ($base - 10), 1);
            $svg .= '<line x1="'.$izq.'" x2="'.$w.'" y1="'.$yy.'" y2="'.$yy.'" class="rejilla"/><text x="'.($izq - 4).'" y="'.($yy + 4).'" class="tx" text-anchor="end">'.round($g * 100).'</text>';
        }
        foreach ($cal as $i => $c) {
            $x0 = $izq + $i * $paso + 5;
            $hp = (float) $c['prob_media'] * ($base - 10);
            $hr = (float) $c['real'] * ($base - 10);
            $svg .= '<g><title>'.self::e($c['rango'].': predijo '.round($c['prob_media'] * 100).' %, pasó '.round($c['real'] * 100).' % ('.$c['casos'].' casos)').'</title>'
                .'<rect x="'.round($x0, 1).'" y="'.round($base - $hp, 1).'" width="'.round($ancho, 1).'" height="'.round($hp, 1).'" class="s2" rx="2"/>'
                .'<rect x="'.round($x0 + $ancho, 1).'" y="'.round($base - $hr, 1).'" width="'.round($ancho, 1).'" height="'.round($hr, 1).'" class="s1" rx="2"/></g>'
                .'<text x="'.round($x0 + $ancho, 1).'" y="158" class="tx" text-anchor="middle">'.self::e($c['rango']).'</text>';
        }
        return $svg.'</svg><div class="leyenda"><span><i class="s2"></i>Predijo</span><span><i class="s1"></i>Pasó de verdad</span></div>';
    }

    // =====================================================================
    // Piezas
    // =====================================================================
    private static function kpi(string $et, string $valor, string $sub, string $tono = ''): string
    {
        return '<div class="kpi '.$tono.'"><span class="ket">'.self::e($et).'</span><b>'.self::e($valor).'</b><span class="ksub">'.self::e($sub).'</span></div>';
    }

    private static function dato(string $et, $v, string $sub = ''): string
    {
        return '<div class="dato"><b>'.self::e((string) $v).'</b><span>'.self::e($et).'</span>'.($sub ? '<small>'.self::e($sub).'</small>' : '').'</div>';
    }

    private static function contar(array $h, string $estado): int
    {
        return count(array_filter($h, fn ($f) => $f['estado'] === $estado));
    }

    private static function nombre(string $c): string
    {
        if (isset(self::NOMBRES[$c])) {
            return self::NOMBRES[$c];
        }
        if (str_starts_with($c, 's_')) {
            return 'Síntoma: '.str_replace('_', ' ', substr($c, 2));
        }
        return str_replace('_', ' ', $c);
    }

    private static function estado(string $e): string
    {
        return ['activo' => 'En uso', 'reemplazado' => 'Aceptado (antes)', 'rechazado' => 'Rechazado',
            'error' => 'Error', 'en_curso' => 'Entrenando…'][$e] ?? $e;
    }

    private static function num($v, int $dec = 3): string
    {
        return $v === null || $v === '' ? '—' : number_format((float) $v, $dec, ',', '.');
    }

    private static function horas(float $h): string
    {
        return $h >= 48 ? round($h / 24, 1).' días' : ($h >= 1 ? round($h).' h' : round($h * 60).' min');
    }

    private static function fecha(?string $f): string
    {
        return $f ? date('d/m/Y H:i', strtotime($f)) : '—';
    }

    private static function e(string $s): string
    {
        return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function pagina(string $cuerpo): string
    {
        return <<<HTML
<!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Aprendizaje del ML</title>
<style>
:root{--fondo:#f6f7f9;--carta:#fff;--texto:#1d2330;--suave:#5d6577;--borde:#e3e6ec;--a1:#2f6fdb;--a2:#9db8ec;--ok:#1f8a5b;--mal:#c2493d;--gris:#9aa2b1;--pista:#eceff4}
@media (prefers-color-scheme:dark){:root{--fondo:#12151b;--carta:#1a1f28;--texto:#e7eaf0;--suave:#a0a8b8;--borde:#2b3240;--a1:#6d9ef0;--a2:#34507f;--ok:#45c08a;--mal:#e5786d;--gris:#6c7486;--pista:#252b36}}
*{box-sizing:border-box}body{margin:0;background:var(--fondo);color:var(--texto);font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
main{max-width:980px;margin:0 auto;padding:28px 16px 60px}
h1{margin:0;font-size:26px}h2{margin:0 0 6px;font-size:19px}h3{margin:22px 0 6px;font-size:15px}
.sub,.nota{color:var(--suave);margin:2px 0 10px}.nota{font-size:13.5px}
header{margin-bottom:18px}
section{background:var(--carta);border:1px solid var(--borde);border-radius:12px;padding:18px 20px;margin:14px 0}
.kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px;background:none;border:0;padding:0}
.kpi{background:var(--carta);border:1px solid var(--borde);border-radius:12px;padding:14px 16px;display:flex;flex-direction:column;gap:2px}
.kpi b{font-size:22px;word-break:break-all}.ket{color:var(--suave);font-size:13px}.ksub{color:var(--suave);font-size:12.5px}
.kpi.ok{border-color:var(--ok)}.kpi.ok .ksub{color:var(--ok);font-weight:600}
.mini{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;margin-top:8px}
.dato{border:1px solid var(--borde);border-radius:10px;padding:10px 12px;display:flex;flex-direction:column}.dato b{font-size:22px}.dato span{color:var(--suave);font-size:13px}.dato small{color:var(--suave)}
.prog{margin:10px 0}.pet{display:flex;justify-content:space-between;gap:10px;font-size:14px;margin-bottom:4px}
.pista{display:block;background:var(--pista);border-radius:6px;height:10px;overflow:hidden;flex:1}.pista .b{display:block;height:100%;background:var(--a1);border-radius:6px}.pista .b.lleno{background:var(--ok)}
.estado{margin:12px 0 0;padding:10px 12px;background:var(--pista);border-radius:8px;font-weight:500}
.barras .fila{display:grid;grid-template-columns:minmax(120px,280px) 1fr 64px;gap:10px;align-items:center;margin:6px 0;font-size:14px}
.barras .v{text-align:right;font-variant-numeric:tabular-nums;color:var(--suave)}
.grafico{width:100%;height:auto;display:block}.eje{stroke:var(--borde)}.rejilla{stroke:var(--borde);stroke-dasharray:3 4}
.ref{stroke:var(--gris);stroke-dasharray:6 5}.linea{fill:none;stroke:var(--a1);stroke-width:2.5}
.tx{fill:var(--suave);font-size:11px}.s1{fill:var(--a1)}.s2{fill:var(--a2)}
circle.p,i.p{fill:var(--gris);stroke:var(--carta);stroke-width:2}circle.activo{fill:var(--ok)}circle.reemplazado{fill:var(--a1)}circle.rechazado,circle.error{fill:var(--mal)}
.leyenda{display:flex;flex-wrap:wrap;gap:14px;font-size:13px;color:var(--suave);margin-top:6px;align-items:center}
.leyenda i{display:inline-block;width:11px;height:11px;border-radius:3px;margin-right:5px;vertical-align:-1px}
.leyenda i.s1{background:var(--a1)}.leyenda i.s2{background:var(--a2)}.leyenda i.p{border-radius:50%;background:var(--gris)}.leyenda i.activo{background:var(--ok)}.leyenda i.reemplazado{background:var(--a1)}.leyenda i.rechazado{background:var(--mal)}
table{width:100%;border-collapse:collapse;font-size:13.5px;margin-top:12px}th,td{text-align:left;padding:7px 8px;border-bottom:1px solid var(--borde);vertical-align:top}th{color:var(--suave);font-weight:600}
td.nw{white-space:nowrap}td.motivo{color:var(--suave);min-width:180px}
.chip{display:inline-block;padding:1px 8px;border-radius:20px;font-size:12px;font-weight:600;background:var(--pista);white-space:nowrap}
.chip.activo{color:var(--ok)}.chip.rechazado,.chip.error{color:var(--mal)}.chip.reemplazado{color:var(--a1)}
.vacio{color:var(--suave);font-style:italic}
.bien{color:var(--ok);font-weight:600}.malo{color:var(--mal);font-weight:600}.gris{color:var(--suave)}
code{font-family:ui-monospace,Consolas,monospace;font-size:12.5px;background:var(--pista);padding:1px 5px;border-radius:4px}
.aviso{background:#fff3cd;color:#664d03;border-radius:10px;padding:10px 14px;margin:10px 0}
@media (max-width:620px){.barras .fila{grid-template-columns:1fr 54px}.barras .et{grid-column:1/-1}table{display:block;overflow-x:auto}}
</style></head>
<body><main>{$cuerpo}</main></body></html>
HTML;
    }
}
